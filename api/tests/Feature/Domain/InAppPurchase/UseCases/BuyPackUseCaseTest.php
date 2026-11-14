<?php

namespace Tests\Feature\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\UseCases\BuyPackUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstInAppPurchase;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
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
        $this->useSessionPlayer($this->sysPlayerId);
        // UseCase は body の platform と X-Platform（ApiSession）の一致を最初に確かめる。
        // 既定は Google。Apple で買うテストは呼ぶ前に setPlatform('Apple') する
        ApiSession::setPlatform('Google');
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
        $mstInAppPurchase = $this->createProduct('pack');
        $this->createPackContents($mstInAppPurchase->getId());

        $result = app(BuyPackUseCase::class)->handle(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'google_play',
            'purchase-token-pack',
            'GPA.PACK-0001',
            'pack_starter'
        );

        $this->queryManager->execAllQuery();

        // 無償ダイヤは trx_item の free_diamond に入る（trx_diamond には free_amount の列が無い）
        $freeDiamond = DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->first();
        $this->assertSame(300, $freeDiamond->free_amount);
        $this->assertSame(0, $freeDiamond->paid_amount);

        // パックは有償ダイヤを含まないので trx_diamond・trx_diamond_balance の行は作らない
        $this->assertSame(0, DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->count());
        $this->assertSame(0, DB::connection('trx1')->table('trx_diamond_balance')
            ->where('sys_player_id', $this->sysPlayerId)->count());

        $item = DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'item_potion_001')->first();
        $this->assertSame(5, $item->free_amount);

        // rewards の無償ダイヤは他のアイテムと同じ形（type: item, item_id: free_diamond）。
        // 応答は rewards と updated_resources だけで、paid_diamond_amount は載せない
        $array = $result->toArray();
        $this->assertEqualsCanonicalizing(['rewards', 'updated_resources'], array_keys($array));
        $this->assertContains(
            ['type' => 'item', 'item_id' => 'free_diamond', 'amount' => 300],
            $array['rewards'],
        );

        $this->assertVipPointAndLogsRecorded();
    }

    #[Test]
    public function ユニット入りのパックを購入できる(): void
    {
        // grade を入れずに new TrxUnit() を組むと NOT NULL で落ち、
        // 採番前の id を返そうとすると TypeError になる。
        // ユニットの組み立てはRepositoryに任せる
        $mstInAppPurchase = $this->createProduct('pack');
        $this->createUnitContent($mstInAppPurchase->getId(), amount: 2);

        $result = app(BuyPackUseCase::class)->handle(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'google_play',
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

        $rewards = $result->toArray()['rewards'];
        $this->assertSame('unit', $rewards[0]['type']);
        $this->assertSame('unit_001', $rewards[0]['mst_unit_id']);
        $this->assertSame(2, $rewards[0]['amount']);
    }

    #[Test]
    public function 三種類のコンテンツをまとめて付与できる(): void
    {
        $mstInAppPurchase = $this->createProduct('pack');
        $this->createPackContents($mstInAppPurchase->getId());
        $this->createUnitContent($mstInAppPurchase->getId());

        $result = app(BuyPackUseCase::class)->handle(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'google_play',
            'purchase-token-pack',
            'GPA.PACK-0003',
            'pack_all'
        );

        $this->queryManager->execAllQuery();

        $this->assertCount(3, $result->toArray()['rewards']);

        $this->assertSame(300, DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount'));
        $this->assertSame(5, DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'item_potion_001')->value('free_amount'));
        $this->assertSame(1, DB::connection('trx1')->table('trx_unit')
            ->where('sys_player_id', $this->sysPlayerId)->count());
    }

    #[Test]
    public function 中身が空のパックでも購入履歴は残る(): void
    {
        $mstInAppPurchase = $this->createProduct('pack');

        $result = app(BuyPackUseCase::class)->handle(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'google_play',
            'purchase-token-pack',
            'GPA.PACK-0004',
            'pack_empty'
        );

        $this->queryManager->execAllQuery();

        // 付与が無いときは rewards キーごと出さない
        $this->assertArrayNotHasKey('rewards', $result->toArray());

        $this->assertDatabaseHas('trx_in_app_purchase', [
            'sys_player_id' => $this->sysPlayerId,
            'mst_in_app_purchase_id' => $mstInAppPurchase->getId(),
        ], 'trx1');
    }

    #[Test]
    public function 購入回数は積み上がる(): void
    {
        $mstInAppPurchase = $this->createProduct('pack');
        $this->createPackContents($mstInAppPurchase->getId());

        foreach (['GPA.PACK-1001', 'GPA.PACK-1002'] as $orderId) {
            app(BuyPackUseCase::class)->handle(
                $this->sysPlayerId,
                $mstInAppPurchase,
                'Google',
                'google_play',
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
        $this->assertSame(600, DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount'));
    }

    #[Test]
    public function 購入回数の上限に達すると次から買えない(): void
    {
        $mstInAppPurchase = $this->createProduct('pack', purchaseLimitCount: 1);
        $this->createPackContents($mstInAppPurchase->getId());

        $this->buy($mstInAppPurchase, 'GPA.PACK-2001');

        try {
            $this->buy($mstInAppPurchase, 'GPA.PACK-2002');
            $this->fail('上限に達しているのに2回目が通ってしまった');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::PURCHASE_LIMIT_EXCEEDED, $e->getErrorCode());
        }

        // 2回目は何も付与されない
        $this->assertSame(300, DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount'));
        $this->assertSame(1, DB::connection('trx1')->table('trx_in_app_purchase')
            ->where('sys_player_id', $this->sysPlayerId)->value('purchase_count'));
    }

    #[Test]
    public function 日次の上限は日付が変われば買い直せる(): void
    {
        $mstInAppPurchase = $this->createProduct('pack', purchaseLimitCount: 1, purchaseLimitReset: 'daily');
        $this->createPackContents($mstInAppPurchase->getId());

        ClockUtility::setNow('2026-03-15 12:00:00');
        $this->buy($mstInAppPurchase, 'GPA.PACK-2101');

        ClockUtility::setNow('2026-03-16 12:00:00');
        $this->buy($mstInAppPurchase, 'GPA.PACK-2102');

        $this->assertSame(600, DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount'));

        ClockUtility::reset();
    }

    /**
     * 1回ぶん購入してフラッシュする
     */
    private function buy(MstInAppPurchase $mstInAppPurchase, string $orderId): void
    {
        app(BuyPackUseCase::class)->handle(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'google_play',
            'purchase-token-'.$orderId,
            $orderId,
            'pack_limited'
        );

        $this->queryManager->execAllQuery();
        $this->queryManager->clear();
    }

    /**
     * パックにユニットのコンテンツを足す
     */
    private function createUnitContent(int $mstInAppPurchaseId, int $amount = 1): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_content')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'mst_in_app_purchase_id' => $mstInAppPurchaseId,
            'content_type' => 'unit',
            'content_mst_id' => 'unit_001',
            'content_quantity' => 1,
            'amount' => $amount,
            'sort_desc' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
