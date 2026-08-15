<?php

namespace Tests\Feature\Mailbox;

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
        ], 'trx1');
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
        ], 'trx1');
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
        ], 'trx1');
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
    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/mailbox/lock', [
            'trx_mailbox_id' => 1,
            'is_locked' => true,
        ])->assertStatus(401);

        $this->postJson('/api/mailbox/receive_all', [])->assertStatus(401);
    }

    private function makeMailbox(int $sysPlayerId, bool $isProtected = false): TrxMailbox
    {
        ApiSession::setSysPlayerId($sysPlayerId);

        return _BaseModel::allowDirectWrites(function () use ($sysPlayerId, $isProtected) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $sysPlayerId,
                'mst_mailbox_id' => 'mailbox_test_001',
                'is_opened' => false,
                'is_received' => false,
                'is_protected' => $isProtected,
                'expires_at' => now()->addDays(30),
            ]);
            $mailbox->setConnection('trx1');
            $mailbox->save();

            return $mailbox;
        });
    }
}
