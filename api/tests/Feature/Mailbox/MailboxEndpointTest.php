<?php

namespace Tests\Feature\Mailbox;

use App\Exceptions\GameErrorCode;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Nexus\Core\Models\_BaseModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * メールボックスのエンドポイント検証
 *
 * 未テストだった lock / receive_all を中心にカバーする。
 */
class MailboxEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_lock_toggles_protection(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($player->id);

        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/mailbox/lock', [
                'trx_mailbox_id' => $mailbox->id,
                'is_locked' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('trx_mailbox', [
            'id' => $mailbox->id,
            'is_protected' => true,
        ], $this->playerConnection($player->id));
    }

    #[Test]
    public function test_lock_can_be_released(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($player->id, isProtected: true);

        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/mailbox/lock', [
                'trx_mailbox_id' => $mailbox->id,
                'is_locked' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('trx_mailbox', [
            'id' => $mailbox->id,
            'is_protected' => false,
        ], $this->playerConnection($player->id));
    }

    #[Test]
    public function test_receive_all_marks_mailboxes_as_received(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($player->id);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/mailbox/receive_all', []);

        $response->assertOk();

        $this->assertDatabaseHas('trx_mailbox', [
            'id' => $mailbox->id,
            'is_received' => true,
        ], $this->playerConnection($player->id));
    }

    #[Test]
    public function test_receive_all_returns_business_error_when_nothing_to_receive(): void
    {
        ['token' => $token] = $this->signUpPlayer();

        // 受取対象が無い場合はビジネスエラー（HTTP 299）
        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/mailbox/receive_all', [])
            ->assertStatus(299);
    }

    #[Test]
    public function test_lock_rejects_deleted_mailbox(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($player->id, isDelete: true);

        // 削除済みはビジネスエラー（HTTP 299）で弾く
        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/mailbox/lock', [
                'trx_mailbox_id' => $mailbox->id,
                'is_locked' => true,
            ])
            ->assertStatus(299)
            ->assertJsonPath('error_code', GameErrorCode::MAILBOX_ALREADY_DELETED);

        $this->assertDatabaseHas('trx_mailbox', [
            'id' => $mailbox->id,
            'is_protected' => false,
        ], $this->playerConnection($player->id));
    }

    #[Test]
    public function test_lock_rejects_other_players_mailbox(): void
    {
        ['player' => $owner] = $this->signUpPlayer();
        ['token' => $otherToken] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($owner->id);

        $this->withHeaders($this->authHeaders($otherToken))
            ->postJson('/api/mailbox/lock', [
                'trx_mailbox_id' => $mailbox->id,
                'is_locked' => true,
            ])
            ->assertStatus(299);
    }

    #[Test]
    public function test_list_returns_mailboxes(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($player->id);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/mailbox/list');

        $response->assertOk();
        $response->assertJsonPath('mailbox_array.0.trx_mailbox_id', $mailbox->id);
    }

    #[Test]
    public function test_list_returns_datetime_columns_as_string(): void
    {
        // expires_at / read_at / received_at はキャストしていないため生の文字列で入っている。
        // レスポンス側がCarbonのつもりで触ると落ちるので、文字列で返ることを固定する。
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->makeMailbox($player->id);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/mailbox/list');

        $response->assertOk();
        $this->assertIsString($response->json('mailbox_array.0.expires_at'));
        $this->assertNull($response->json('mailbox_array.0.read_at'));
        $this->assertNull($response->json('mailbox_array.0.received_at'));
    }

    #[Test]
    public function test_list_is_empty_when_player_has_no_mailbox(): void
    {
        ['token' => $token] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/mailbox/list')
            ->assertOk()
            ->assertJsonPath('mailbox_array', [])
            ->assertJsonPath('total_unread', 0);
    }

    #[Test]
    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/mailbox/list')->assertStatus(401);

        $this->postJson('/api/mailbox/lock', [
            'trx_mailbox_id' => 1,
            'is_locked' => true,
        ])->assertStatus(401);

        $this->postJson('/api/mailbox/receive_all', [])->assertStatus(401);
    }

    private function makeMailbox(int $sysPlayerId, bool $isProtected = false, bool $isDelete = false): TrxMailbox
    {
        ApiSession::setSysPlayerId($sysPlayerId);

        $connection = $this->playerConnection($sysPlayerId);

        return _BaseModel::allowDirectWrites(function () use ($sysPlayerId, $isProtected, $isDelete, $connection) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $sysPlayerId,
                'mst_mailbox_id' => 'mailbox_test_001',
                'is_opened' => false,
                'is_received' => false,
                'is_delete' => $isDelete,
                'is_protected' => $isProtected,
                'expires_at' => now()->addDays(30),
            ]);
            $mailbox->setConnection($connection);
            $mailbox->save();

            return $mailbox;
        });
    }
}
