<?php

namespace Tests\Feature\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\UseCases\BuyPassUseCase;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * パス購入のテスト
 *
 * トランザクションを _BaseBuyUseCase に集約したため、
 * 購入・VIP付与・課金ログが1つのトランザクションで完了することを確認する。
 */
class BuyPassUseCaseTest extends TestCase
{
    use PreparesPurchaseFixtures;
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    private const PRICE_MICROS = 980_000_000;

    private const VIP_POINT = 98;

    private int $sysPlayerId = 1;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的に制御するため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $this->queryManager = app(QueryManager::class);

        config([
            'services.google_play.package_name' => 'com.example.nexus',
            'services.google_play.service_account' => json_encode([
                'client_email' => 'nexus@example.iam.gserviceaccount.com',
                'private_key' => $this->generatePrivateKey(),
            ]),
        ]);

        cache()->flush();
        $this->cleanUp();
        $this->createPlayer();
        $this->fakeGooglePlay();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function パス購入で有償ダイヤと効果が付与される(): void
    {
        $mstInAppPurchase = $this->createProduct('Pass', paidDiamondAmount: 100, effectDurationDays: 30);
        $this->createPassEffect($mstInAppPurchase->getId());

        $response = app(BuyPassUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'GooglePlay',
            'purchase-token-pass',
            'GPA.PASS-0001',
            'pass_monthly'
        );

        $this->queryManager->execAllQuery();

        $this->assertSame(100, $response->toArray()['paid_diamond_amount']);

        $diamond = DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertSame(100, $diamond->paid_amount);

        // パス効果が有効期限付きで登録される
        $effect = DB::connection('trx1')->table('trx_in_app_purchase_effect')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertNotNull($effect);
        $this->assertSame('ExpBoost', $effect->effect_type);
        $this->assertSame(1, (int) $effect->is_active);

        $this->assertVipPointAndLogsRecorded();
    }
}
