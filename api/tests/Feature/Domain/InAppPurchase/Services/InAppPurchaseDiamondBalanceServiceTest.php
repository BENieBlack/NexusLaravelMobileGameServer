<?php

namespace Tests\Feature\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Services\InAppPurchaseDiamondBalanceService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\TransactionDataException;
use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxDiamondBalanceRepository;
use App\Repositories\Trx\TrxDiamondRepository;
use App\Repositories\Trx\TrxItemRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * InAppPurchaseDiamondBalanceServiceのテスト
 *
 * 無償ダイヤは trx_item の free_diamond、有償ダイヤは trx_diamond（X-Platform の行）と
 * trx_diamond_balance（購入単位。古い順 FIFO）。消費は無償 → 有償の順で、
 * 有償の消費は paid_amount と balance の current_amount を同じトランザクションで減らす。
 *
 * このテストは QueryManager を直接操作する（execAllQuery がコミット相当）ので、
 * 自動のトランザクションは外し、tearDown で作った行を消す。
 */
class InAppPurchaseDiamondBalanceServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    private string $platform = 'Apple';

    private InAppPurchaseDiamondBalanceService $diamondBalanceService;

    private QueryManager $queryManager;

    /**
     * Override to prevent automatic transaction wrapping
     * because we need to manually control transactions with QueryManager
     */
    public function beginDatabaseTransaction(): void
    {
        // Do nothing - let tests manage their own transactions
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->openSession();

        $this->diamondBalanceService = app(InAppPurchaseDiamondBalanceService::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        // Clear all test data
        foreach (['trx_diamond', 'trx_diamond_balance', 'trx_item'] as $table) {
            DB::connection('trx1')->table($table)->delete();
        }

        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function 有償ダイヤモンドを加算できる(): void
    {
        $this->diamondBalanceService->addPaidDiamond($this->sysPlayerId, $this->platform, 500);
        $this->queryManager->execAllQuery();

        $this->assertSame(500, $this->paidAmount($this->platform));
        // addPaidDiamond は購入単位の行を作らない（運営調整などの経路）
        $this->assertSame(0, DB::connection('trx1')->table('trx_diamond_balance')->where('sys_player_id', $this->sysPlayerId)->count());
    }

    #[Test]
    public function 購入の加算はpaid_amountと購入単位の行を同時に作る(): void
    {
        $this->diamondBalanceService->addPaidDiamondWithBalance($this->sysPlayerId, $this->platform, 'AppStore', 500, 1.5);
        $this->queryManager->execAllQuery();

        $this->assertSame(500, $this->paidAmount($this->platform));
        $balances = $this->balances($this->platform);
        $this->assertCount(1, $balances);
        $this->assertSame(500, $balances[0]->current_amount);
        $this->assertSame(500, $balances[0]->purchase_amount);
    }

    #[Test]
    public function 無償ダイヤモンドを優先的に消費する(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 1000);
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 500);
        $this->openSession();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 300, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        $this->assertSame(700, $this->freeAmount());  // 1000 - 300
        $this->assertSame(500, $this->paidAmount($this->platform));  // 有償は触らない
        $this->assertSame(500, $this->balances($this->platform)[0]->current_amount);  // balance も触らない
    }

    #[Test]
    public function 無償を使い切った後に有償を消費し行は0で残る(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 1000);
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 500);
        $this->openSession();

        // 1200 = 無償 1000 + 有償 200
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 1200, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        $this->assertSame(0, $this->freeAmount());  // 0 になっても行は消さない
        $this->assertSame(1, DB::connection('trx1')->table('trx_item')->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->count());
        $this->assertSame(300, $this->paidAmount($this->platform));
        $this->assertSame(300, $this->balances($this->platform)[0]->current_amount);  // paid_amount と連動
    }

    #[Test]
    public function 有償ダイヤモンドのみを消費できる(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 1000);
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 500);
        $this->openSession();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 200, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(1000, $this->freeAmount());  // 無償は減らない
        $this->assertSame(300, $this->paidAmount($this->platform));
    }

    #[Test]
    public function 有償のみの消費は無償の行が無くても行を作らない(): void
    {
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 500);
        $this->openSession();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 100, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(0, DB::connection('trx1')->table('trx_item')->where('sys_player_id', $this->sysPlayerId)->count());
        $this->assertSame(400, $this->paidAmount($this->platform));
    }

    #[Test]
    public function 無償だけで足りるときはtrx_diamondとbalanceを触らない(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 100);
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 500);
        $this->openSession();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 100, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        $this->assertSame(0, $this->freeAmount());
        $this->assertSame(500, $this->paidAmount($this->platform));
        $this->assertSame(500, $this->balances($this->platform)[0]->current_amount);
    }

    #[Test]
    public function 所持数は無償と_x_platformの有償の合計(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 30);
        $this->seedPaidDiamond($this->sysPlayerId, 'Apple', 500);
        $this->seedPaidDiamond($this->sysPlayerId, 'Google', 900);
        $this->openSession('Apple');

        // 他 platform（Google）の有償は数えない
        $this->assertSame(530, $this->diamondBalanceService->findDiamondAmount($this->sysPlayerId, isPaidOnly: false));
        $this->assertSame(500, $this->diamondBalanceService->findDiamondAmount($this->sysPlayerId, isPaidOnly: true));
    }

    #[Test]
    public function 他platformの有償は消費に使えず_行にも触らない(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 10);
        $this->seedPaidDiamond($this->sysPlayerId, 'Google', 900);
        $this->openSession('Apple');

        // Apple の有償は 0。無償 10 + 有償 0 では 11 に足りない
        try {
            $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 11, isPaidOnly: false);
            $this->fail('例外が発生するはず');
        } catch (\Exception $e) {
            $this->assertStringContainsString('ダイヤモンド残高が不足しています', $e->getMessage());
        }

        $this->queryManager->execAllQuery();
        $this->assertSame(900, $this->paidAmount('Google'));
        $this->assertSame(900, $this->balances('Google')[0]->current_amount);
        $this->assertSame(10, $this->freeAmount());
    }

    #[Test]
    public function platformごとに独立して消費できる(): void
    {
        $this->seedPaidDiamond($this->sysPlayerId, 'Apple', 500);
        $this->seedPaidDiamond($this->sysPlayerId, 'Google', 800);
        $this->openSession('Google');

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 300, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(500, $this->paidAmount('Apple'));
        $this->assertSame(500, $this->paidAmount('Google'));
        $this->assertSame(500, $this->balances('Apple')[0]->current_amount);
        $this->assertSame(500, $this->balances('Google')[0]->current_amount);
    }

    #[Test]
    public function balanceは作成日時の古い順に引き_足りない分は次の行へ回る(): void
    {
        // 作成順と id 順が逆になるよう、新しい行を先に入れる
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 300, '2026-03-02 00:00:00');
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-01 00:00:00');
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 200, '2026-03-03 00:00:00');
        $this->openSession();

        // 古い順: 100（03-01）→ 300（03-02）→ 200（03-03）。250 は 100 を使い切り、次の行から 150
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 250, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(350, $this->paidAmount($this->platform));
        $this->assertSame(
            ['2026-03-01 00:00:00' => 0, '2026-03-02 00:00:00' => 150, '2026-03-03 00:00:00' => 200],
            $this->balanceAmountsByCreatedAt($this->platform),
        );
        // 使い切った行も消さずに 0 で残る
        $this->assertCount(3, $this->balances($this->platform));
    }

    #[Test]
    public function 同じ秒の行はidの小さい順に引く(): void
    {
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-01 00:00:00');
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-01 00:00:00');
        $this->openSession();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 100, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $rows = $this->balances($this->platform);  // id 昇順
        $this->assertSame([0, 100], [$rows[0]->current_amount, $rows[1]->current_amount]);
    }

    #[Test]
    public function 使い切った行は次の消費で対象にならない(): void
    {
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-01 00:00:00');
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-02 00:00:00');
        $this->openSession();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 100, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->openSession();
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 50, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(
            ['2026-03-01 00:00:00' => 0, '2026-03-02 00:00:00' => 50],
            $this->balanceAmountsByCreatedAt($this->platform),
        );
        $this->assertSame(50, $this->paidAmount($this->platform));
    }

    #[Test]
    public function 同じトランザクションで2回消費しても2回目は1回目の結果から計算する(): void
    {
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-01 00:00:00');
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100, '2026-03-02 00:00:00');
        $this->openSession();

        // DB へ反映する前に 2 回。1 回目の 80 を引いた 20 から 2 回目が始まる
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 80, isPaidOnly: true);
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 50, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(70, $this->paidAmount($this->platform));
        $this->assertSame(
            ['2026-03-01 00:00:00' => 0, '2026-03-02 00:00:00' => 70],
            $this->balanceAmountsByCreatedAt($this->platform),
        );
    }

    #[Test]
    public function 購入と同じトランザクションの消費でも未保存の購入行から引ける(): void
    {
        $this->diamondBalanceService->addPaidDiamondWithBalance($this->sysPlayerId, $this->platform, 'AppStore', 100, 1.0);
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 30, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        $this->assertSame(70, $this->paidAmount($this->platform));
        $this->assertSame(70, $this->balances($this->platform)[0]->current_amount);
    }

    #[Test]
    public function balanceの合計がpaid_amountに満たないデータ不整合は例外で全体を巻き戻す(): void
    {
        // 不整合: paid_amount は 500 だが balance は 300 しかない（運営の直書きなど）
        DB::connection('trx1')->table('trx_diamond')->insert([
            'sys_player_id' => $this->sysPlayerId, 'platform' => $this->platform, 'paid_amount' => 500,
        ]);
        DB::connection('trx1')->table('trx_diamond_balance')->insert([
            'sys_player_id' => $this->sysPlayerId, 'platform' => $this->platform, 'billing_platform' => 'AppStore',
            'current_amount' => 300, 'purchase_amount' => 300, 'unit_price' => 1.00,
        ]);
        $this->seedFreeDiamond($this->sysPlayerId, 50);
        $this->openSession();

        try {
            // 無償 50 + 有償 350。有償の 350 は balance の 300 では引ききれない
            $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 400, isPaidOnly: false);
            $this->fail('例外が発生するはず');
        } catch (TransactionDataException $e) {
            // DIAMOND_NOT_ENOUGH（残高不足）ではなくデータ不整合として扱う
            $this->assertNotSame(GameErrorCode::DIAMOND_NOT_ENOUGH, $e->getCode());
        }

        // 例外でキューを捨てる（UseCase のトランザクションが巻き戻す動き）。何も書かれていない
        $this->queryManager->clear();
        $this->queryManager->execAllQuery();

        $this->assertSame(50, $this->freeAmount());
        $this->assertSame(500, $this->paidAmount($this->platform));
        $this->assertSame(300, $this->balances($this->platform)[0]->current_amount);
    }

    #[Test]
    public function balanceの減算は汎用の変更ログlog_change_trxに_updat_eとして残る(): void
    {
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100);
        $this->openSession();
        DB::connection('trx1')->table('log_change_trx')->where('sys_player_id', $this->sysPlayerId)->delete();

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 40, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        // trx_diamond_balance の専用ログ表（log_change_trx_diamond_balance）には書き込む処理が無く、
        // 実際の記録はこの汎用ログ。current_amount の変更が JSON に出る
        $log = DB::connection('trx1')->table('log_change_trx')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('table_name', 'trx_diamond_balance')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('UPDATE', $log->operation);
        $this->assertSame(60, json_decode($log->after_data, true)['current_amount']);

        DB::connection('trx1')->table('log_change_trx')->where('sys_player_id', $this->sysPlayerId)->delete();
    }

    #[Test]
    public function 残高不足の場合は例外が発生し何も書かない(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 100);
        $this->openSession();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ダイヤモンド残高が不足しています');

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 200, isPaidOnly: false);
    }

    #[Test]
    public function 有償のみで有償が足りなければ無償があっても例外(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 1000);
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 100);
        $this->openSession();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ダイヤモンド残高が不足しています');

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 200, isPaidOnly: true);
    }

    #[Test]
    public function 何も持たないプレイヤーの消費は例外(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ダイヤモンド残高が不足しています');

        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 100, isPaidOnly: false);
    }

    #[Test]
    public function platformが未設定なら暗黙の既定値を使わず例外(): void
    {
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);  // platform は設定しない

        $this->expectException(\RuntimeException::class);

        $this->diamondBalanceService->findDiamondAmount($this->sysPlayerId, isPaidOnly: true);
    }

    #[Test]
    public function 大量のダイヤモンドを正確に消費できる(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 100000);
        $this->seedPaidDiamond($this->sysPlayerId, $this->platform, 50000);
        $this->openSession();

        // 120000 = 無償 100000 + 有償 20000
        $this->diamondBalanceService->consumeDiamond($this->sysPlayerId, 120000, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        $this->assertSame(0, $this->freeAmount());
        $this->assertSame(30000, $this->paidAmount($this->platform));
        $this->assertSame(30000, $this->balances($this->platform)[0]->current_amount);
    }

    /**
     * セッション（プレイヤー + X-Platform）を張り直す。
     * DB へ反映した後に、リクエストをまたいだ状態（Repository のキャッシュ）を捨てるためにも使う
     */
    private function openSession(string $platform = 'Apple'): void
    {
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);
        ApiSession::setPlatform($platform);
        $this->flushRepositoryCaches();
    }

    /**
     * Repository のメモリキャッシュを捨てる
     *
     * 本番はリクエストごとにプロセス内の状態が新しいが、テストは同じアプリで複数回の「リクエスト」を
     * 続けるため、DB へ直接入れたフィクスチャを Repository に読ませ直す必要がある
     */
    private function flushRepositoryCaches(): void
    {
        app(TrxDiamondBalanceRepository::class)->forgetCachedModels();
        app(TrxDiamondRepository::class)->forgetCachedModels();
        app(TrxItemRepository::class)->forgetCachedModels();
    }

    private function freeAmount(): int
    {
        return (int) DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'free_diamond')
            ->value('free_amount');
    }

    private function paidAmount(string $platform): int
    {
        return (int) DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $platform)
            ->value('paid_amount');
    }

    /**
     * @return list<object> id 昇順
     */
    private function balances(string $platform): array
    {
        return DB::connection('trx1')->table('trx_diamond_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $platform)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return array<string, int> created_at => current_amount
     */
    private function balanceAmountsByCreatedAt(string $platform): array
    {
        $result = [];
        foreach ($this->balances($platform) as $row) {
            $result[$row->created_at] = $row->current_amount;
        }
        ksort($result);

        return $result;
    }
}
