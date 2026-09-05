<?php

namespace Tests\Feature\Chat;

use App\Exceptions\GameErrorCode;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * チャットのエンドポイントのテスト
 *
 * 権限（誰が招待・キック・削除できるか）と、非メンバーが部屋を覗けない
 * ことを重点的に固定する。チャットは sys に置くためシャードを跨がない。
 */
class ChatEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $ownerPlayerId;

    private string $ownerToken;

    private int $otherPlayerId;

    private string $otherToken;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $owner, 'token' => $ownerToken] = $this->signUpPlayer();
        $this->ownerPlayerId = $owner->id;
        $this->ownerToken = $ownerToken;

        ['player' => $other, 'token' => $otherToken] = $this->signUpPlayer();
        $this->otherPlayerId = $other->id;
        $this->otherToken = $otherToken;
    }

    protected function tearDown(): void
    {
        DB::connection('sys')->table('sys_chat_message')->delete();
        DB::connection('sys')->table('sys_chat_room_member')->delete();
        DB::connection('sys')->table('sys_chat_room')->delete();

        parent::tearDown();
    }

    // =========================================================
    // フレンドDM
    // =========================================================

    #[Test]
    public function フレンドの個別チャット部屋を作れる(): void
    {
        $response = $this->postJson(
            '/api/chat/friend/room',
            ['sys_player_id' => $this->otherPlayerId],
            $this->authHeaders($this->ownerToken)
        );

        $response->assertOk();
        $this->assertSame('friend', $response->json('room.type'));
        $this->assertSame(2, $response->json('room.member_count'));
        // クライアントはこのチャンネル名を購読する
        $this->assertStringStartsWith('private-chat.friend.', $response->json('room.channel_name'));
    }

    #[Test]
    public function 同じ相手なら何度開いても同じ部屋になる(): void
    {
        // room_key は小さいID_大きいIDなので、どちらから開いても同じ
        $first = $this->postJson(
            '/api/chat/friend/room',
            ['sys_player_id' => $this->otherPlayerId],
            $this->authHeaders($this->ownerToken)
        )->json('room.sys_chat_room_id');

        $second = $this->postJson(
            '/api/chat/friend/room',
            ['sys_player_id' => $this->ownerPlayerId],
            $this->authHeaders($this->otherToken)
        )->json('room.sys_chat_room_id');

        $this->assertSame($first, $second);
    }

    // =========================================================
    // メッセージ
    // =========================================================

    #[Test]
    public function メッセージを送って履歴で読める(): void
    {
        $roomId = $this->makeFriendRoom();

        $this->postJson(
            '/api/chat/message/send',
            ['sys_chat_room_id' => $roomId, 'body' => 'こんにちは'],
            $this->authHeaders($this->ownerToken)
        )->assertOk();

        $response = $this->getJson(
            '/api/chat/messages?sys_chat_room_id='.$roomId,
            $this->authHeaders($this->otherToken)
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('messages'));
        $this->assertSame('こんにちは', $response->json('messages.0.body'));
        $this->assertSame($this->ownerPlayerId, $response->json('messages.0.sender_sys_player_id'));
    }

    #[Test]
    public function 空白だけのメッセージはバリデーションで弾く(): void
    {
        // TrimStringsミドルウェアが空白を落とすため、パッケージまで届かず
        // requiredで422になる。パッケージ側の空文字判定は他の経路の保険
        $roomId = $this->makeFriendRoom();

        $this->postJson(
            '/api/chat/message/send',
            ['sys_chat_room_id' => $roomId, 'body' => '   '],
            $this->authHeaders($this->ownerToken)
        )->assertUnprocessable();
    }

    #[Test]
    public function 上限を超えるメッセージは業務エラーで返る(): void
    {
        $roomId = $this->makeFriendRoom();

        $response = $this->postJson(
            '/api/chat/message/send',
            ['sys_chat_room_id' => $roomId, 'body' => str_repeat('あ', 501)],
            $this->authHeaders($this->ownerToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_MESSAGE_TOO_LONG, $response->json('error_code'));
    }

    #[Test]
    public function 部屋のメンバーでなければ履歴を読めない(): void
    {
        $roomId = $this->makeFriendRoom();

        ['token' => $strangerToken] = $this->signUpPlayer();

        $response = $this->getJson(
            '/api/chat/messages?sys_chat_room_id='.$roomId,
            $this->authHeaders($strangerToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_NOT_ROOM_MEMBER, $response->json('error_code'));
    }

    #[Test]
    public function 自分のメッセージは削除できる(): void
    {
        $roomId = $this->makeFriendRoom();
        $messageId = $this->postJson(
            '/api/chat/message/send',
            ['sys_chat_room_id' => $roomId, 'body' => '取り消したい'],
            $this->authHeaders($this->ownerToken)
        )->json('message.sys_chat_message_id');

        $this->postJson(
            '/api/chat/message/delete',
            ['sys_chat_message_id' => $messageId],
            $this->authHeaders($this->ownerToken)
        )->assertOk();

        $messages = $this->getJson(
            '/api/chat/messages?sys_chat_room_id='.$roomId,
            $this->authHeaders($this->ownerToken)
        )->json('messages');

        $this->assertSame([], $messages, '削除済みは履歴に出ない');
    }

    #[Test]
    public function 他人のメッセージは削除できない(): void
    {
        $roomId = $this->makeFriendRoom();
        $messageId = $this->postJson(
            '/api/chat/message/send',
            ['sys_chat_room_id' => $roomId, 'body' => '消させない'],
            $this->authHeaders($this->ownerToken)
        )->json('message.sys_chat_message_id');

        $response = $this->postJson(
            '/api/chat/message/delete',
            ['sys_chat_message_id' => $messageId],
            $this->authHeaders($this->otherToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_NOT_MESSAGE_OWNER, $response->json('error_code'));
    }

    // =========================================================
    // グループチャット
    // =========================================================

    #[Test]
    public function グループを作ると作成者がオーナーになる(): void
    {
        $response = $this->postJson(
            '/api/chat/group/create',
            ['name' => '仲良し'],
            $this->authHeaders($this->ownerToken)
        );

        $response->assertOk();
        $roomId = $response->json('room.sys_chat_room_id');
        $this->assertSame('group', $response->json('room.type'));

        $members = $this->getJson(
            '/api/chat/group/members?sys_chat_room_id='.$roomId,
            $this->authHeaders($this->ownerToken)
        );

        $members->assertOk();
        $this->assertCount(1, $members->json('members'));
        $this->assertSame('owner', $members->json('members.0.role'));
        $this->assertSame($this->ownerPlayerId, $members->json('members.0.sys_player_id'));
    }

    #[Test]
    public function オーナーはメンバーを招待できる(): void
    {
        $roomId = $this->makeGroupRoom();

        $response = $this->postJson(
            '/api/chat/group/invite',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $this->otherPlayerId],
            $this->authHeaders($this->ownerToken)
        );

        $response->assertOk();
        $this->assertSame('member', $response->json('member.role'));
        $this->assertSame(2, $this->findRoom($roomId)->member_count);
    }

    #[Test]
    public function 一般メンバーは招待できない(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        ['player' => $third] = $this->signUpPlayer();

        $response = $this->postJson(
            '/api/chat/group/invite',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $third->id],
            $this->authHeaders($this->otherToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_NO_INVITE_PERMISSION, $response->json('error_code'));
    }

    #[Test]
    public function 既にいるメンバーは招待できない(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        $response = $this->postJson(
            '/api/chat/group/invite',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $this->otherPlayerId],
            $this->authHeaders($this->ownerToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_ALREADY_MEMBER, $response->json('error_code'));
    }

    #[Test]
    public function オーナーはメンバーをキックできる(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        $this->postJson(
            '/api/chat/group/kick',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $this->otherPlayerId],
            $this->authHeaders($this->ownerToken)
        )->assertOk();

        $this->assertSame(1, $this->findRoom($roomId)->member_count);
    }

    #[Test]
    public function 一般メンバーはキックできない(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        $response = $this->postJson(
            '/api/chat/group/kick',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $this->ownerPlayerId],
            $this->authHeaders($this->otherToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_NO_KICK_PERMISSION, $response->json('error_code'));
    }

    #[Test]
    public function オーナーはロールを変えられる(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        $this->postJson(
            '/api/chat/group/role',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $this->otherPlayerId, 'role' => 'admin'],
            $this->authHeaders($this->ownerToken)
        )->assertOk();

        $members = $this->getJson(
            '/api/chat/group/members?sys_chat_room_id='.$roomId,
            $this->authHeaders($this->ownerToken)
        )->json('members');

        $roles = array_column($members, 'role', 'sys_player_id');
        $this->assertSame('admin', $roles[$this->otherPlayerId]);
    }

    #[Test]
    public function 一般メンバーはロールを変えられない(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        $response = $this->postJson(
            '/api/chat/group/role',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $this->ownerPlayerId, 'role' => 'member'],
            $this->authHeaders($this->otherToken)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_NO_ROLE_MANAGE_PERMISSION, $response->json('error_code'));
    }

    #[Test]
    public function 退室するとメンバー数が減る(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId);

        $this->postJson(
            '/api/chat/group/leave',
            ['sys_chat_room_id' => $roomId],
            $this->authHeaders($this->otherToken)
        )->assertOk();

        $this->assertSame(1, $this->findRoom($roomId)->member_count);
    }

    #[Test]
    public function 参加中のルーム一覧を取得できる(): void
    {
        $this->makeFriendRoom();
        $this->makeGroupRoom();

        $response = $this->getJson('/api/chat/rooms', $this->authHeaders($this->ownerToken));

        $response->assertOk();
        $this->assertCount(2, $response->json('rooms'));
    }

    #[Test]
    public function ギルドに入っていなければギルドチャットは開けない(): void
    {
        $response = $this->postJson('/api/chat/guild/room', [], $this->authHeaders($this->ownerToken));

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::CHAT_NOT_GUILD_MEMBER, $response->json('error_code'));
    }

    #[Test]
    public function 認証なしではチャットを扱えない(): void
    {
        $this->getJson('/api/chat/rooms')->assertUnauthorized();
        $this->postJson('/api/chat/group/create', ['name' => 'x'])->assertUnauthorized();
        $this->postJson('/api/chat/message/send', ['sys_chat_room_id' => 1, 'body' => 'x'])->assertUnauthorized();
    }

    #[Test]
    public function 必須パラメータが無いリクエストは弾く(): void
    {
        $this->postJson('/api/chat/group/create', [], $this->authHeaders($this->ownerToken))
            ->assertUnprocessable();
        $this->postJson('/api/chat/message/send', [], $this->authHeaders($this->ownerToken))
            ->assertUnprocessable();
    }

    // =========================================================
    // ヘルパ
    // =========================================================

    private function makeFriendRoom(): int
    {
        return (int) $this->postJson(
            '/api/chat/friend/room',
            ['sys_player_id' => $this->otherPlayerId],
            $this->authHeaders($this->ownerToken)
        )->json('room.sys_chat_room_id');
    }

    private function makeGroupRoom(string $name = '仲良し'): int
    {
        return (int) $this->postJson(
            '/api/chat/group/create',
            ['name' => $name],
            $this->authHeaders($this->ownerToken)
        )->json('room.sys_chat_room_id');
    }

    private function invite(int $roomId, int $targetPlayerId): void
    {
        $this->postJson(
            '/api/chat/group/invite',
            ['sys_chat_room_id' => $roomId, 'sys_player_id' => $targetPlayerId],
            $this->authHeaders($this->ownerToken)
        )->assertOk();
    }

    private function findRoom(int $roomId): object
    {
        $row = DB::connection('sys')->table('sys_chat_room')->where('id', $roomId)->first();

        $this->assertNotNull($row);

        return $row;
    }
}
