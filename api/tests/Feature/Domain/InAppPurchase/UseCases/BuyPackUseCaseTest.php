<?php

namespace Tests\Feature\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\UseCases\BuyPackUseCase;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * パック購入のテスト
 *
 * トランザクションを _BaseBuyUseCase に集約したため、
 * 購入・VIP付与・課金ログが1つのトランザクションで完了することを確認する。
 */
class BuyPackUseCaseTest extends TestCase
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
    public function パック購入で無償ダイヤとアイテムが付与される(): void
    {
        $mstInAppPurchase = $this->createProduct('Pack');
        $this->createPackContents($mstInAppPurchase->getId());

        $response = app(BuyPackUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'GooglePlay',
            'purchase-token-pack',
            'GPA.PACK-0001',
            'pack_starter'
        );

        $this->queryManager->execAllQuery();

        $this->assertSame(300, $response->toArray()['total_free_diamond_amount']);

        $diamond = DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertSame(300, $diamond->free_amount);
        $this->assertSame(0, $diamond->paid_amount);

        $item = DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertSame('item_potion_001', $item->mst_item_id);
        $this->assertSame(5, $item->free_amount);

        $this->assertVipPointAndLogsRecorded();
    }
}
