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

    #[Test]
    public function ユニット入りのパックを購入できる(): void
    {
        // grade を入れずに new TrxUnit() を組むと NOT NULL で落ち、
        // 採番前の id を返そうとすると TypeError になる。
        // ユニットの組み立てはRepositoryに任せる
        $mstInAppPurchase = $this->createProduct('Pack');
        $this->createUnitContent($mstInAppPurchase->getId(), amount: 2);

        $response = app(BuyPackUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'GooglePlay',
            'purchase-token-pack',
            'GPA.PACK-0002',
            'pack_unit'
        );

        $this->queryManager->execAllQuery();

        $units = DB::connection('trx1')->table('trx_unit')
            ->where('sys_player_id', $this->sysPlayerId)->get();

        $this->assertCount(2, $units, '個数ぶんのユニットが作られる');
        $this->assertSame('unit_001', $units[0]->mst_unit_id);
        $this->assertSame(1, $units[0]->grade, 'グレードの初期値が入る');
        $this->assertSame(1, $units[0]->level);
        $this->assertSame(0, (int) $units[0]->level_exp);

        $rewards = $response->toArray()['rewards'];
        $this->assertSame('Unit', $rewards[0]['type']);
        $this->assertSame('unit_001', $rewards[0]['mst_unit_id']);
        $this->assertSame(2, $rewards[0]['amount']);
    }

    #[Test]
    public function 三種類のコンテンツをまとめて付与できる(): void
    {
        $mstInAppPurchase = $this->createProduct('Pack');
        $this->createPackContents($mstInAppPurchase->getId());
        $this->createUnitContent($mstInAppPurchase->getId());

        $response = app(BuyPackUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'GooglePlay',
            'purchase-token-pack',
            'GPA.PACK-0003',
            'pack_all'
        );

        $this->queryManager->execAllQuery();

        $this->assertSame(300, $response->toArray()['total_free_diamond_amount']);
        $this->assertCount(3, $response->toArray()['rewards']);

        $this->assertSame(300, DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->value('free_amount'));
        $this->assertSame(5, DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->value('free_amount'));
        $this->assertSame(1, DB::connection('trx1')->table('trx_unit')
            ->where('sys_player_id', $this->sysPlayerId)->count());
    }

    #[Test]
    public function 中身が空のパックでも購入履歴は残る(): void
    {
        $mstInAppPurchase = $this->createProduct('Pack');

        $response = app(BuyPackUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'GooglePlay',
            'purchase-token-pack',
            'GPA.PACK-0004',
            'pack_empty'
        );

        $this->queryManager->execAllQuery();

        // 付与が無いときは rewards キーごと出さない
        $this->assertArrayNotHasKey('rewards', $response->toArray());
        $this->assertSame(0, $response->toArray()['total_free_diamond_amount']);

        $this->assertDatabaseHas('trx_in_app_purchase', [
            'sys_player_id' => $this->sysPlayerId,
            'mst_in_app_purchase_id' => $mstInAppPurchase->getId(),
        ], 'trx1');
    }

    #[Test]
    public function 購入回数は積み上がる(): void
    {
        $mstInAppPurchase = $this->createProduct('Pack');
        $this->createPackContents($mstInAppPurchase->getId());

        foreach (['GPA.PACK-1001', 'GPA.PACK-1002'] as $orderId) {
            app(BuyPackUseCase::class)->exec(
                $this->sysPlayerId,
                $mstInAppPurchase,
                'Google',
                'GooglePlay',
                'purchase-token-'.$orderId,
                $orderId,
                'pack_starter'
            );
            $this->queryManager->execAllQuery();
            $this->queryManager->clear();
        }

        $history = DB::connection('trx1')->table('trx_in_app_purchase')
            ->where('sys_player_id', $this->sysPlayerId)->first();

        $this->assertSame(2, $history->purchase_count);
        $this->assertSame(600, DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->value('free_amount'));
    }

    /**
     * パックにユニットのコンテンツを足す
     */
    private function createUnitContent(int $mstInAppPurchaseId, int $amount = 1): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_content')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'mst_in_app_purchase_id' => $mstInAppPurchaseId,
            'content_type' => 'Unit',
            'content_mst_id' => 'unit_001',
            'content_quantity' => 1,
            'amount' => $amount,
            'sort_desc' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
