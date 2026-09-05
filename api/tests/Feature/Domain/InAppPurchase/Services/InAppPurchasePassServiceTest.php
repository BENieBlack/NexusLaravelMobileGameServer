<?php

namespace Tests\Feature\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Services\InAppPurchasePassService;
use App\Models\Mst\MstInAppPurchase;
use App\Persistence\ApiSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * InAppPurchasePassService のテスト
 *
 * 月額パスの効果を持つのはプレイヤー側の trx_in_app_purchase_effect。
 * 期限が切れた効果が効き続けないこと、複数回買ったぶんが積み上がることが要点。
 */
class InAppPurchasePassServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    private int $sysPlayerId = 1;

    private InAppPurchasePassService $service;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);
        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->service = app(InAppPurchasePassService::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ClockUtility::reset();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    // ========================================
    // applyPassEffects
    // ========================================

    #[Test]
    public function 購入すると効果が有効期限付きで登録される(): void
    {
        $product = $this->createPassProduct(effectDurationDays: 30);

        $this->service->applyPassEffects($this->sysPlayerId, $product);
        $this->flush();

        $effect = $this->findEffects()->first();

        $this->assertNotNull($effect);
        $this->assertSame('exp_boost', $effect->effect_type);
        $this->assertSame('1.50', (string) $effect->value);
        $this->assertSame('2026-04-14 12:00:00', $effect->expires_at, '30日後');
        $this->assertSame(1, (int) $effect->is_active);
    }

    #[Test]
    public function 期間の指定が無ければ30日になる(): void
    {
        $product = $this->createPassProduct(effectDurationDays: null);

        $this->service->applyPassEffects($this->sysPlayerId, $product);
        $this->flush();

        $this->assertSame('2026-04-14 12:00:00', $this->findEffects()->first()->expires_at);
    }

    #[Test]
    public function 効果を持たない商品では何も登録されない(): void
    {
        $product = $this->createPassProduct(withEffect: false);

        $this->service->applyPassEffects($this->sysPlayerId, $product);
        $this->flush();

        $this->assertCount(0, $this->findEffects());
    }

    #[Test]
    public function 買い増すと効果は積み上がる(): void
    {
        // 同じ効果でも上書きせず新しい行を作る（重ねがけを許す）
        $product = $this->createPassProduct(effectDurationDays: 30);

        $this->service->applyPassEffects($this->sysPlayerId, $product);
        $this->flush();

        ClockUtility::setNow('2026-03-20 12:00:00');
        $this->service->applyPassEffects($this->sysPlayerId, $product);
        $this->flush();

        $effects = $this->findEffects();

        $this->assertCount(2, $effects);
        $this->assertSame('2026-04-14 12:00:00', $effects[0]->expires_at);
        $this->assertSame('2026-04-19 12:00:00', $effects[1]->expires_at, '買った日から数え直す');
    }

    // ========================================
    // findActiveEffects
    // ========================================

    #[Test]
    public function 有効な効果だけが返る(): void
    {
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00');
        $this->insertEffect('gold_boost', 2.00, expiresAt: '2026-03-01 00:00:00');

        $active = $this->service->findActiveEffects($this->sysPlayerId);

        $this->assertCount(1, $active);
        $this->assertSame('exp_boost', $active->first()->getAttribute('effect_type'));
    }

    #[Test]
    public function 期限切れの効果には論理削除フラグが立つ(): void
    {
        // 毎回読み飛ばすのではなく、掃除できるように印を付ける
        $expiredId = $this->insertEffect('gold_boost', 2.00, expiresAt: '2026-03-01 00:00:00');
        $aliveId = $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00');

        $this->service->findActiveEffects($this->sysPlayerId);
        $this->flush();

        $this->assertSame(1, (int) $this->findEffect($expiredId)->is_delete);
        $this->assertSame(0, (int) $this->findEffect($aliveId)->is_delete);
    }

    #[Test]
    public function 手動で無効にした効果も返らない(): void
    {
        // 期限内でも is_active が落ちていれば効かない
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00', isActive: false);

        $this->assertCount(0, $this->service->findActiveEffects($this->sysPlayerId));
    }

    #[Test]
    public function 効果が無ければ空で返る(): void
    {
        $this->assertCount(0, $this->service->findActiveEffects($this->sysPlayerId));
    }

    // ========================================
    // calcTotalEffectValue
    // ========================================

    #[Test]
    public function 同じ効果タイプの値を合計する(): void
    {
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00');
        $this->insertEffect('exp_boost', 0.25, expiresAt: '2026-04-01 00:00:00');
        $this->insertEffect('gold_boost', 2.00, expiresAt: '2026-04-01 00:00:00');

        // decimal は文字列で入っているので、数値として畳めていることを見る
        $this->assertSame(1.75, $this->service->calcTotalEffectValue($this->sysPlayerId, 'exp_boost'));
        $this->assertSame(2.0, $this->service->calcTotalEffectValue($this->sysPlayerId, 'gold_boost'));
    }

    #[Test]
    public function 期限切れの効果は合計に入らない(): void
    {
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00');
        $this->insertEffect('exp_boost', 5.00, expiresAt: '2026-03-01 00:00:00');

        $this->assertSame(1.5, $this->service->calcTotalEffectValue($this->sysPlayerId, 'exp_boost'));
    }

    #[Test]
    public function 該当する効果が無ければ0(): void
    {
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00');

        $this->assertSame(0.0, (float) $this->service->calcTotalEffectValue($this->sysPlayerId, 'ad_skip'));
    }

    // ========================================
    // deactivatePassEffects
    // ========================================

    #[Test]
    public function 商品を指定して効果を無効にできる(): void
    {
        $targetId = $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00', mstInAppPurchaseId: 10);
        $otherId = $this->insertEffect('gold_boost', 2.00, expiresAt: '2026-04-01 00:00:00', mstInAppPurchaseId: 20);

        $this->service->deactivatePassEffects(10);
        $this->flush();

        $this->assertSame(0, (int) $this->findEffect($targetId)->is_active);
        $this->assertSame(1, (int) $this->findEffect($otherId)->is_active, '別商品の効果は触らない');
    }

    #[Test]
    public function 無効にした効果はもう返らない(): void
    {
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00', mstInAppPurchaseId: 10);

        $this->service->deactivatePassEffects(10);

        $this->assertCount(0, $this->service->findActiveEffects($this->sysPlayerId));
    }

    #[Test]
    public function 対象の効果が無ければ何も起きない(): void
    {
        $this->insertEffect('exp_boost', 1.50, expiresAt: '2026-04-01 00:00:00', mstInAppPurchaseId: 10);

        $this->service->deactivatePassEffects(999);
        $this->flush();

        $this->assertCount(1, $this->service->findActiveEffects($this->sysPlayerId));
    }

    private function flush(): void
    {
        $this->queryManager->execAllQuery();
        $this->queryManager->clear();
    }

    /**
     * @return Collection<int, object>
     */
    private function findEffects(): Collection
    {
        return DB::connection('trx1')->table('trx_in_app_purchase_effect')
            ->where('sys_player_id', $this->sysPlayerId)
            ->orderBy('id')
            ->get();
    }

    private function findEffect(int $id): object
    {
        $row = DB::connection('trx1')->table('trx_in_app_purchase_effect')->where('id', $id)->first();

        $this->assertNotNull($row);

        return $row;
    }

    private function insertEffect(
        string $effectType,
        float $value,
        string $expiresAt,
        bool $isActive = true,
        int $mstInAppPurchaseId = 1,
    ): int {
        return DB::connection('trx1')->table('trx_in_app_purchase_effect')->insertGetId([
            'sys_player_id' => $this->sysPlayerId,
            'mst_in_app_purchase_id' => $mstInAppPurchaseId,
            'effect_type' => $effectType,
            'value' => $value,
            'expires_at' => $expiresAt,
            'is_active' => $isActive,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPassProduct(?int $effectDurationDays = 30, bool $withEffect = true): MstInAppPurchase
    {
        $id = DB::connection('mst')->table('mst_in_app_purchase')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY,
            'type' => 'pass',
            'paid_diamond_amount' => 100,
            'vip_point' => 98,
            'effect_duration_days' => $effectDurationDays,
            'purchase_limit_reset' => 'none',
            'sort_desc' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($withEffect) {
            DB::connection('mst')->table('mst_in_app_purchase_effect')->insert([
                'deploy_key' => self::DEPLOY_KEY,
                'mst_in_app_purchase_id' => $id,
                'effect_type' => 'exp_boost',
                'value' => 1.50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return MstInAppPurchase::on('mst')->findOrFail($id);
    }

    private function cleanUp(): void
    {
        DB::connection('trx1')->table('trx_in_app_purchase_effect')->delete();
        DB::connection('mst')->table('mst_in_app_purchase_effect')->delete();
        DB::connection('mst')->table('mst_in_app_purchase')->delete();
        $this->refreshMstCache();
    }
}
