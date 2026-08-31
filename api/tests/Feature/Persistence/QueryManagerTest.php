<?php

namespace Tests\Feature\Persistence;

use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use App\Repositories\Log\LogInAppPurchaseRepository;
use App\Repositories\Trx\TrxMailboxRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * QueryManager のテスト
 *
 * Repositoryが積んだ変更をまとめて書き込む、永続化の中核。
 *
 * flush() はTrxDBへの書き込みのみで、ログは execAllLogs() で別途書く。
 * 課金ログを別枠にする仕組み（execPurchaseQuery）も用意されているが、
 * 現状そちらへ振り分けられておらず通常ログと同じ経路に乗る。
 * ここでは実際の挙動を書き留める。
 */
class QueryManagerTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $connection;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // 明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $this->connection = $this->playerConnection($this->sysPlayerId);

        $this->queryManager = app(QueryManager::class);
        $this->queryManager->clear();
    }

    protected function tearDown(): void
    {
        DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->delete();

        foreach (['log1', 'log2'] as $connection) {
            DB::connection($connection)->table('log_in_app_purchase')
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        ApiSession::clearForTest();
        ClockUtility::reset();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function フラッシュするまで書き込まれない(): void
    {
        $this->queueMailbox();

        $this->assertSame(0, $this->countMailbox(), 'setModelの時点ではまだ書かない');

        $this->queryManager->flush();

        $this->assertSame(1, $this->countMailbox());
    }

    #[Test]
    public function フラッシュ後にキューは空になる(): void
    {
        $this->queueMailbox();
        $this->queryManager->flush();

        // 2回目のフラッシュで同じ行がもう一度入らない
        $this->queryManager->flush();

        $this->assertSame(1, $this->countMailbox());
    }

    #[Test]
    public function clearすると積んだ変更は捨てられる(): void
    {
        $this->queueMailbox();

        $this->queryManager->clear();
        $this->queryManager->flush();

        $this->assertSame(0, $this->countMailbox(), 'clearした分は書き込まれない');
    }

    #[Test]
    public function 同じrepositoryを重複登録しない(): void
    {
        $repository = app(TrxMailboxRepository::class);

        // setModelのたびに登録されるが、実体は1つ
        $this->queueMailbox();
        $this->queueMailbox('mailbox_test_002');
        $this->queryManager->flush();

        $this->assertSame(2, $this->countMailbox(), '重複登録で二重書き込みにならない');
        $this->assertInstanceOf(TrxMailboxRepository::class, $repository);
    }

    #[Test]
    public function flushはログを書かない(): void
    {
        $this->queueInAppPurchaseLog();

        $this->queryManager->flush();

        $this->assertSame(0, $this->countPurchaseLog(), 'flush()はTrxDBへの書き込みのみ');
    }

    #[Test]
    public function 課金ログもexec_all_logsで書かれる(): void
    {
        // LogInAppPurchaseRepository は isPurchaseLog = true を宣言しているが、
        // 現状その値は読まれておらず、通常ログと同じ経路に乗っている
        $this->queueInAppPurchaseLog();

        $this->queryManager->execAllLogs();

        $this->assertSame(1, $this->countPurchaseLog());
    }

    #[Test]
    public function ログは二重に書かれない(): void
    {
        $this->queueInAppPurchaseLog();

        $this->queryManager->execAllLogs();
        $this->queryManager->execAllLogs();

        $this->assertSame(1, $this->countPurchaseLog(), '実行後にキューを空にしている');
    }

    #[Test]
    public function 課金ログ用の枠は現状使われていない(): void
    {
        // execPurchaseQuery() は purchaseLogRepositories だけを見るが、
        // そこへ登録する経路が無いため何も書かれない。
        // 仕組みを活かすなら setModel() に isPurchaseLog を渡す必要がある
        $this->queueInAppPurchaseLog();

        $this->queryManager->execPurchaseQuery();

        $this->assertSame(0, $this->countPurchaseLog());
    }

    #[Test]
    public function 通常ログはexec_all_logsで書かれる(): void
    {
        $this->queueMailbox();

        $this->queryManager->execAllQuery();
        $this->queryManager->execAllLogs();

        $this->assertSame(1, $this->countMailbox());
    }

    #[Test]
    public function 積むものが無ければ何も起きない(): void
    {
        $this->queryManager->flush();
        $this->queryManager->execPurchaseQuery();
        $this->queryManager->execAllLogs();

        $this->assertSame(0, $this->countMailbox());
        $this->assertSame(0, $this->countPurchaseLog());
    }

    #[Test]
    public function 課金ログ枠へ登録すればexec_purchase_queryで書ける(): void
    {
        // 振り分ける経路はまだ無いが、直接登録すれば仕組みは動く。
        // 課金だけ先に確定させたい場合に使う想定
        $this->queueInAppPurchaseLog();
        $this->queryManager->registerRepository(app(LogInAppPurchaseRepository::class), isPurchaseLog: true);

        $this->queryManager->execPurchaseQuery();

        $this->assertSame(1, $this->countPurchaseLog());
    }

    #[Test]
    public function 課金ログ枠は一度書いたら空になる(): void
    {
        $this->queueInAppPurchaseLog();
        $this->queryManager->registerRepository(app(LogInAppPurchaseRepository::class), isPurchaseLog: true);
        $this->queryManager->execPurchaseQuery();

        // 続けて execAllLogs を呼んでも二重に書かれない
        $this->queryManager->execAllLogs();

        $this->assertSame(1, $this->countPurchaseLog());
    }

    #[Test]
    public function 課金ログ枠も重複登録しない(): void
    {
        $repository = app(LogInAppPurchaseRepository::class);
        $this->queueInAppPurchaseLog();

        $this->queryManager->registerRepository($repository, isPurchaseLog: true);
        $this->queryManager->registerRepository($repository, isPurchaseLog: true);
        $this->queryManager->execPurchaseQuery();

        $this->assertSame(1, $this->countPurchaseLog());
    }

    #[Test]
    public function clearは課金ログ枠も捨てる(): void
    {
        $this->queueInAppPurchaseLog();
        $this->queryManager->registerRepository(app(LogInAppPurchaseRepository::class), isPurchaseLog: true);

        $this->queryManager->clear();
        $this->queryManager->execPurchaseQuery();

        $this->assertSame(0, $this->countPurchaseLog());
    }

    #[Test]
    public function ログの書き込みに失敗したら例外を投げ直す(): void
    {
        // ビジネスデータと同じトランザクションで書くため、
        // ここで握り潰すとログだけ欠けた状態でコミットされる
        $failing = \Mockery::mock(LogInAppPurchaseRepository::class)->makePartial();
        $failing->shouldReceive('getQueuedModels')->andThrow(new \RuntimeException('log db is down'));

        $this->queryManager->registerRepository($failing, isPurchaseLog: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('log db is down');

        $this->queryManager->execAllLogs();
    }

    private function queueMailbox(string $mstMailboxId = 'mailbox_test_001'): void
    {
        $repository = app(TrxMailboxRepository::class);

        $mailbox = new TrxMailbox([
            'sys_player_id' => $this->sysPlayerId,
            'mst_mailbox_id' => $mstMailboxId,
            'is_opened' => false,
            'is_received' => false,
        ]);
        $mailbox->exists = false;

        $method = new \ReflectionMethod($repository, 'setModel');
        $method->setAccessible(true);
        $method->invoke($repository, $mailbox);
    }

    private function queueInAppPurchaseLog(): void
    {
        app(LogInAppPurchaseRepository::class)->insertPurchaseLog(
            uniqueRequestId: 'request-'.uniqid(),
            sysPlayerId: $this->sysPlayerId,
            platform: 'Google',
            billingPlatform: 'GooglePlay',
            receiptId: 'receipt-'.uniqid(),
            receipt: [],
            status: 'completed',
            mstInAppPurchaseId: '1',
            currencyCode: 'JPY',
            payAmount: 480.0,
            payString: '480',
        );
    }

    private function countMailbox(): int
    {
        return DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->count();
    }

    private function countPurchaseLog(): int
    {
        $count = 0;

        foreach (['log1', 'log2'] as $connection) {
            $count += DB::connection($connection)->table('log_in_app_purchase')
                ->where('sys_player_id', $this->sysPlayerId)->count();
        }

        return $count;
    }
}
