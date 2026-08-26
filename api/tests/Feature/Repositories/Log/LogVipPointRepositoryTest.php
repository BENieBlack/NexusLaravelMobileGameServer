<?php

namespace Tests\Feature\Repositories\Log;

use App\Persistence\ApiSession;
use App\Repositories\Log\LogVipPointRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * LogVipPointRepository のテスト
 *
 * VIPポイントの変動ログ。課金との突き合わせに使うため、
 * 記録した内容がそのまま読み戻せることと、期間・理由での絞り込みを確認する。
 */
class LogVipPointRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    private LogVipPointRepository $repository;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->repository = app(LogVipPointRepository::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        ClockUtility::reset();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function 変動ログを記録して読み戻せる(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->repository->log(
            uniqueRequestId: 'request-1',
            sysPlayerId: $this->sysPlayerId,
            beforeLevel: 0,
            afterLevel: 1,
            beforePoint: 0,
            afterPoint: 500,
            pointDiff: 500,
            reason: 'purchase',
            metadata: [
                'purchase_amount' => 480.0,
                'currency_code' => 'JPY',
                'mst_in_app_purchase_id' => 'diamond_pack_001',
            ],
        );
        $this->flushLogs();

        $log = $this->repository->selectByUniqueRequestId('request-1');

        $this->assertNotNull($log);
        $this->assertSame(0, $log->getBeforeVipLevel());
        $this->assertSame(1, $log->getAfterVipLevel());
        $this->assertSame(500, $log->getAfterVipPoint());
        $this->assertSame(500, $log->getPointDiff());
        $this->assertSame('purchase', $log->getReason());
        $this->assertSame('JPY', $log->getCurrencyCode());
        $this->assertSame('diamond_pack_001', $log->getMstInAppPurchaseId());
    }

    #[Test]
    public function 課金情報が無い変動も記録できる(): void
    {
        $this->log('request-2', reason: 'event_bonus');
        $this->flushLogs();

        $log = $this->repository->selectByUniqueRequestId('request-2');

        $this->assertNotNull($log);
        $this->assertNull($log->getPurchaseAmount());
        $this->assertNull($log->getCurrencyCode());
        $this->assertNull($log->getMstInAppPurchaseId());
    }

    #[Test]
    public function 存在しないリクエストidはnullを返す(): void
    {
        $this->assertNull($this->repository->selectByUniqueRequestId('no-such-request'));
    }

    #[Test]
    public function 履歴は新しい順に返る(): void
    {
        $this->log('request-old', at: '2026-03-15 10:00:00', afterPoint: 100);
        $this->log('request-new', at: '2026-03-15 12:00:00', afterPoint: 300);
        $this->flushLogs();

        $history = $this->repository->selectHistory($this->sysPlayerId);

        $this->assertCount(2, $history);
        $this->assertSame(300, $history[0]['after_vip_point'], '新しいログが先頭に来る');
        $this->assertSame(100, $history[1]['after_vip_point']);
        $this->assertArrayHasKey('system_at', $history[0]);
    }

    #[Test]
    public function 履歴は件数を制限できる(): void
    {
        $this->log('request-1', at: '2026-03-15 10:00:00');
        $this->log('request-2', at: '2026-03-15 11:00:00');
        $this->flushLogs();

        $this->assertCount(1, $this->repository->selectHistory($this->sysPlayerId, limit: 1));
    }

    #[Test]
    public function 期間で絞り込める(): void
    {
        $this->log('request-before', at: '2026-03-14 23:59:59');
        $this->log('request-in', at: '2026-03-15 12:00:00');
        $this->log('request-after', at: '2026-03-16 00:00:01');
        $this->flushLogs();

        $logs = $this->repository->selectByPeriod(
            $this->sysPlayerId,
            '2026-03-15 00:00:00',
            '2026-03-16 00:00:00'
        );

        $this->assertCount(1, $logs);
        $this->assertSame('request-in', $logs->first()->getUniqueRequestId());
    }

    #[Test]
    public function 理由で絞り込める(): void
    {
        $this->log('request-purchase', reason: 'purchase');
        $this->log('request-bonus', reason: 'event_bonus');
        $this->flushLogs();

        $logs = $this->repository->selectByReason($this->sysPlayerId, 'purchase');

        $this->assertCount(1, $logs);
        $this->assertSame('request-purchase', $logs->first()->getUniqueRequestId());
    }

    private function log(
        string $uniqueRequestId,
        string $reason = 'purchase',
        ?string $at = null,
        int $afterPoint = 500,
    ): void {
        if ($at !== null) {
            ClockUtility::setNow($at);
        }

        $this->repository->log(
            uniqueRequestId: $uniqueRequestId,
            sysPlayerId: $this->sysPlayerId,
            beforeLevel: 0,
            afterLevel: 1,
            beforePoint: 0,
            afterPoint: $afterPoint,
            pointDiff: $afterPoint,
            reason: $reason,
        );
    }

    /**
     * ログはトランザクション外でまとめて書き込まれる
     */
    private function flushLogs(): void
    {
        $this->queryManager->execAllQuery();
        $this->queryManager->execAllLogs();
    }

    private function cleanUp(): void
    {
        foreach (['log1', 'log2', 'log3'] as $connection) {
            DB::connection($connection)->table('log_vip_point')->delete();
        }
    }
}
