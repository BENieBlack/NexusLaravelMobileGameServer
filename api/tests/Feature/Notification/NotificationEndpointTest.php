<?php

namespace Tests\Feature\Notification;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ゲーム内通知のエンドポイントのテスト
 *
 * 通知はプレイヤーごとのシャードに入る。IDだけで既読にできてしまうと
 * 他人の通知を潰せるため、所有者の判定を重点的に固定する。
 */
class NotificationEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $token;

    private string $connection;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->token = $token;
        $this->connection = $this->playerConnection($this->sysPlayerId);
    }

    protected function tearDown(): void
    {
        DB::connection($this->connection)->table('trx_notification')
            ->where('sys_player_id', $this->sysPlayerId)->delete();

        parent::tearDown();
    }

    #[Test]
    public function 通知一覧と未読数を取得できる(): void
    {
        $this->makeNotification('friend_apply_received', 'フレンド申請');
        $this->makeNotification('mailbox_received', 'メールが届きました', isRead: true);

        $response = $this->getJson('/api/notification/list', $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertCount(2, $response->json('notifications'));
        $this->assertSame(1, $response->json('unread_count'));
        // 新しいものから返す
        $this->assertSame('mailbox_received', $response->json('notifications.0.type'));
    }

    #[Test]
    public function 未読だけに絞れる(): void
    {
        $this->makeNotification('friend_apply_received', 'フレンド申請');
        $this->makeNotification('mailbox_received', 'メール', isRead: true);

        $response = $this->getJson('/api/notification/list?only_unread=1', $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertCount(1, $response->json('notifications'));
        $this->assertSame('friend_apply_received', $response->json('notifications.0.type'));
    }

    #[Test]
    public function 通知に付随データが載る(): void
    {
        $this->makeNotification('friend_apply_received', 'フレンド申請', payload: ['sys_friend_apply_id' => 9]);

        $response = $this->getJson('/api/notification/list', $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertSame(['sys_friend_apply_id' => 9], $response->json('notifications.0.payload'));
    }

    #[Test]
    public function 通知を既読にできる(): void
    {
        $id = $this->makeNotification('friend_apply_received', 'フレンド申請');

        $response = $this->postJson(
            '/api/notification/read',
            ['trx_notification_id' => $id],
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $this->assertSame(0, $response->json('unread_count'));
        $this->assertTrue((bool) $this->findNotification($id)->is_read);
        $this->assertNotNull($this->findNotification($id)->read_at);
    }

    #[Test]
    public function 他人の通知は既読にできない(): void
    {
        ['player' => $other] = $this->signUpPlayer();
        $othersId = $this->makeNotification('mailbox_received', 'メール', sysPlayerId: $other->id);

        $this->postJson(
            '/api/notification/read',
            ['trx_notification_id' => $othersId],
            $this->authHeaders($this->token)
        )->assertOk();

        $row = DB::connection($this->playerConnection($other->id))
            ->table('trx_notification')->where('id', $othersId)->first();

        $this->assertFalse((bool) $row->is_read, '他人の通知は未読のまま');

        DB::connection($this->playerConnection($other->id))
            ->table('trx_notification')->where('sys_player_id', $other->id)->delete();
    }

    #[Test]
    public function 全件を既読にできる(): void
    {
        $this->makeNotification('friend_apply_received', 'フレンド申請');
        $this->makeNotification('mailbox_received', 'メール');

        $response = $this->postJson('/api/notification/read_all', [], $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertSame(0, $response->json('unread_count'));
        $this->assertSame(0, DB::connection($this->connection)->table('trx_notification')
            ->where('sys_player_id', $this->sysPlayerId)->where('is_read', false)->count());
    }

    #[Test]
    public function 存在しない通知の既読はエラーにしない(): void
    {
        // 二重タップや古い通知の再送で落とさない
        $this->postJson(
            '/api/notification/read',
            ['trx_notification_id' => 999999],
            $this->authHeaders($this->token)
        )->assertOk();
    }

    #[Test]
    public function 通知の指定が無いリクエストは弾く(): void
    {
        $this->postJson('/api/notification/read', [], $this->authHeaders($this->token))
            ->assertUnprocessable();
    }

    #[Test]
    public function 認証なしでは通知を扱えない(): void
    {
        $this->getJson('/api/notification/list')->assertUnauthorized();
        $this->postJson('/api/notification/read', ['trx_notification_id' => 1])->assertUnauthorized();
        $this->postJson('/api/notification/read_all')->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeNotification(
        string $type,
        string $title,
        bool $isRead = false,
        array $payload = [],
        ?int $sysPlayerId = null,
    ): int {
        $sysPlayerId ??= $this->sysPlayerId;

        return DB::connection($this->playerConnection($sysPlayerId))
            ->table('trx_notification')
            ->insertGetId([
                'sys_player_id' => $sysPlayerId,
                'type' => $type,
                'title' => $title,
                'body' => '本文',
                'payload' => $payload === [] ? null : json_encode($payload),
                'is_read' => $isRead,
                'read_at' => $isRead ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function findNotification(int $id): object
    {
        $row = DB::connection($this->connection)->table('trx_notification')->where('id', $id)->first();

        $this->assertNotNull($row);

        return $row;
    }
}
