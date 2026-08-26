<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxDiamondRepository;
use App\Repositories\Trx\TrxInAppPurchaseRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Repositories\Trx\TrxWalletRepository;
use Illuminate\Support\Facades\DB;
use NexusStamina\Constants\StaminaConst;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 複合主キーのテーブルを扱うRepositoryのキャッシュキー検証
 *
 * queryOrMemory() は $uniqueKeys のカラム値を連結してkeyByする。
 * idを持たないテーブルで既定の ['id'] のままだと、キーが全行で
 * 空文字になり1件しかキャッシュに残らない。
 * 該当するRepositoryが主キーを宣言していることを行数で確かめる。
 */
class CompositeUniqueKeyCacheTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $this->connection = $this->playerConnection($this->sysPlayerId);
    }

    protected function tearDown(): void
    {
        foreach (['trx_stamina', 'trx_diamond', 'trx_wallet', 'trx_in_app_purchase'] as $table) {
            DB::connection($this->connection)->table($table)->where('sys_player_id', $this->sysPlayerId)->delete();
        }
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function スタミナはタイプごとにキャッシュされる(): void
    {
        $types = [StaminaConst::TYPE_NORMAL, StaminaConst::TYPE_RAID, StaminaConst::TYPE_PVP];

        foreach ($types as $type) {
            DB::connection($this->connection)->table('trx_stamina')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'type' => $type,
                'current_stamina' => 10,
                'last_recovery_at' => '2026-03-15 12:00:00',
            ]);
        }

        $repository = app(TrxStaminaRepository::class);

        $this->assertCount(3, $repository->queryOrMemory(), 'タイプの数だけキャッシュに残る');

        foreach ($types as $type) {
            $this->assertNotNull($repository->selectByType($type), "{$type} が引けない");
        }
    }

    #[Test]
    public function ダイヤはプラットフォームごとにキャッシュされる(): void
    {
        foreach (['AppStore', 'GooglePlay'] as $platform) {
            DB::connection($this->connection)->table('trx_diamond')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'platform' => $platform,
                'free_amount' => 100,
                'paid_amount' => 50,
            ]);
        }

        $this->assertCount(2, app(TrxDiamondRepository::class)->queryOrMemory());
    }

    #[Test]
    public function 課金履歴は商品ごとにキャッシュされる(): void
    {
        foreach ([['AppStore', 1], ['AppStore', 2], ['GooglePlay', 1]] as [$platform, $productId]) {
            DB::connection($this->connection)->table('trx_in_app_purchase')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'billing_platform' => $platform,
                'mst_in_app_purchase_id' => $productId,
                'total_purchase_count' => 1,
                'purchase_count' => 1,
            ]);
        }

        $this->assertCount(3, app(TrxInAppPurchaseRepository::class)->queryOrMemory());
    }

    #[Test]
    public function ウォレットはアイテムごとにキャッシュされる(): void
    {
        foreach (['item_gold', 'item_silver'] as $mstItemId) {
            DB::connection($this->connection)->table('trx_wallet')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => $mstItemId,
                'free_amount' => 100,
                'paid_amount' => 0,
            ]);
        }

        $this->assertCount(2, app(TrxWalletRepository::class)->queryOrMemory());
    }
}
