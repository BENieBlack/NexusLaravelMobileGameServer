<?php

namespace Tests\Feature\Mailbox;

use App\Exceptions\GameErrorCode;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
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
    public function test_list_with_multiple_mailboxes_does_not_lazy_load_relations(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->makeMailbox($player->id);
        $this->makeMailbox($player->id);

        // 複数件の一覧で、1件ごとにリレーションをSELECTしない（遅延読み込みは本番以外で例外になる）
        $response = $this->withHeaders($this->authHeaders($token))->getJson('/api/mailbox/list');

        $response->assertOk();
        $this->assertCount(2, $response->json('mailbox_list'));
    }

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
        $response->assertJsonStructure(['received_trx_mailbox_id_list', 'total_count', 'skipped_count', 'updated_resources']);

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
        $response->assertJsonPath('mailbox_list.0.trx_mailbox_id', $mailbox->id);
    }

    #[Test]
    public function test_list_response_shape_is_fixed(): void
    {
        // 一覧の応答全体を丸ごと比べて固定する。Result（MailboxItem など）を外す整理で、
        // キー名・値・ラベル・アイコン・テンプレートの描画結果が変わらないことの確認用。
        // 時刻と採番 ID は実行ごとに変わるので、実際の値を期待値に差し込む
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $mailbox = $this->makeMailbox($player->id, mstMailboxId: 'mailbox_shape_001');

        // マスター: メッセージ（ja / en）・メール・添付 2 件（item と custom）
        DB::connection('mst')->table('mst_message')->insert(['id' => 'message_shape_001']);
        foreach (['ja' => ['題名', '本文'], 'en' => ['Title', 'Body']] as $language => [$title, $body]) {
            DB::connection('mst')->table('mst_message__l10n')->insert([
                'mst_message_id' => 'message_shape_001', 'language' => $language, 'title' => $title, 'body' => $body,
            ]);
        }
        DB::connection('mst')->table('mst_mailbox')->insert([
            'id' => 'mailbox_shape_001', 'mst_message_id' => 'message_shape_001',
            'category' => 'reward', 'priority' => 'important', 'sender_type' => 'system',
            'icon_url' => 'https://example.com/icon.png',
        ]);
        foreach ([['item', 'item_shape_001', 5, 'SR', true, 2], ['custom', 'custom_shape_001', 1, null, false, 1]] as [$type, $id, $amount, $rarity, $highlight, $sort]) {
            DB::connection('mst')->table('mst_mailbox_content')->insert([
                'mst_mailbox_id' => 'mailbox_shape_001', 'content_type' => $type, 'content_mst_id' => $id,
                'content_quantity' => $amount, 'amount' => $amount, 'rarity' => $rarity,
                'is_highlight' => $highlight, 'sort_desc' => $sort,
            ]);
        }
        $this->refreshMstCache();

        try {
            $json = $this->withHeaders($this->authHeaders($token))->getJson('/api/mailbox/list')->assertOk()->json();
        } finally {
            DB::connection('mst')->table('mst_mailbox_content')->where('mst_mailbox_id', 'mailbox_shape_001')->delete();
            DB::connection('mst')->table('mst_mailbox')->where('id', 'mailbox_shape_001')->delete();
            DB::connection('mst')->table('mst_message__l10n')->where('mst_message_id', 'message_shape_001')->delete();
            DB::connection('mst')->table('mst_message')->where('id', 'message_shape_001')->delete();
            $this->refreshMstCache();
        }

        $item = $json['mailbox_list'][0];
        // 添付の並びはマスターの取得順に依存する（ORDER BY が無い）ので、content_mst_id 順にそろえて比べる
        usort($item['content_list'], fn (array $a, array $b) => strcmp($a['content_mst_id'], $b['content_mst_id']));
        $this->assertSame([
            'trx_mailbox_id' => $mailbox->id,
            'mst_mailbox_id' => 'mailbox_shape_001',
            'title' => 'Title',
            'body' => 'Body',
            'category' => 'reward',
            'category_label' => '報酬',
            'category_icon' => '🎁',
            'priority' => 'important',
            'priority_label' => '重要',
            'priority_color' => '#FF9800',
            'priority_icon' => '⚠️',
            'sender_type' => 'system',
            'sender_type_label' => 'システム運営',
            'sender_id' => null,
            'sender_name' => null,
            'icon_url' => 'https://example.com/icon.png',
            'is_opened' => false,
            'is_received' => false,
            'is_protected' => false,
            'is_expired' => false,
            'is_unread' => true,
            'expires_at' => $item['expires_at'],
            'read_at' => null,
            'received_at' => null,
            'created_at' => $item['created_at'],
            'content_list' => [
                [
                    'content_type' => 'custom',
                    'content_type_label' => 'カスタム',
                    'content_type_icon' => '❓',
                    'content_mst_id' => 'custom_shape_001',
                    'amount' => 1,
                    'rarity' => null,
                    'is_highlight' => false,
                    'sort_desc' => 1,
                ],
                [
                    'content_type' => 'item',
                    'content_type_label' => 'アイテム',
                    'content_type_icon' => '📦',
                    'content_mst_id' => 'item_shape_001',
                    'amount' => 5,
                    'rarity' => 'SR',
                    'is_highlight' => true,
                    'sort_desc' => 2,
                ],
            ],
            'has_content' => true,
        ], $item);
        $this->assertSame(['reward' => 1], $json['unread_counts']);
        $this->assertSame(1, $json['total_unread']);
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
        $this->assertIsString($response->json('mailbox_list.0.expires_at'));
        $this->assertNull($response->json('mailbox_list.0.read_at'));
        $this->assertNull($response->json('mailbox_list.0.received_at'));
    }

    #[Test]
    public function test_list_can_filter_by_category(): void
    {
        // mst_mailbox は別のDB接続にあるため、JOINで絞ろうとするとSQLエラーになる
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->makeMailbox($player->id);

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/mailbox/list?category=system')
            ->assertOk();
    }

    #[Test]
    public function test_list_can_filter_by_priority(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->makeMailbox($player->id);

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/mailbox/list?priority=normal')
            ->assertOk();
    }

    #[Test]
    public function test_list_is_empty_when_player_has_no_mailbox(): void
    {
        ['token' => $token] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/mailbox/list')
            ->assertOk()
            ->assertJsonPath('mailbox_list', [])
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

    private function makeMailbox(int $sysPlayerId, bool $isProtected = false, bool $isDelete = false, string $mstMailboxId = 'mailbox_test_001'): TrxMailbox
    {
        ApiSession::setSysPlayerId($sysPlayerId);

        $connection = $this->playerConnection($sysPlayerId);

        return _BaseModel::allowDirectWrites(function () use ($sysPlayerId, $isProtected, $isDelete, $connection, $mstMailboxId) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $sysPlayerId,
                'mst_mailbox_id' => $mstMailboxId,
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
