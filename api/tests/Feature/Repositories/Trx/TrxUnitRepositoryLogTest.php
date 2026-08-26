<?php

namespace Tests\Feature\Repositories\Trx;

use App\Models\Trx\TrxUnit;
use App\Persistence\ApiSession;
use App\Repositories\Log\LogUnitRepository;
use App\Repositories\Trx\TrxUnitRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * TrxUnitRepository のログ連動テスト
 *
 * setLogRepository() を差してからユニットを保存すると、
 * afterSave フックが log_unit へ変更前後を書き出す。
 *
 * mst_unit_id は 'unit_knight_001' のような文字列IDなので、
 * ログ側も文字列で受け取れることをここで固定する。
 */
class TrxUnitRepositoryLogTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private TrxUnitRepository $trxUnitRepository;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->queryManager = app(QueryManager::class);
        $this->queryManager->clear();

        $this->trxUnitRepository = app(TrxUnitRepository::class);
        $this->trxUnitRepository->setLogRepository(app(LogUnitRepository::class));
        $this->trxUnitRepository->setUniqueRequestId('request-unit-1');
    }

    protected function tearDown(): void
    {
        foreach (['log1', 'log2', 'log3'] as $connection) {
            DB::connection($connection)->table('log_unit')
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }
        DB::connection('trx1')->table('trx_unit')->where('sys_player_id', $this->sysPlayerId)->delete();

        ApiSession::clearForTest();
        ClockUtility::reset();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function ユニットのレベルアップがログに残る(): void
    {
        $unit = $this->makeUnit('unit_knight_001', level: 3, levelExp: 100);

        $unit->setLevel(4);
        $unit->setLevelExp(250);
        $this->setModel($unit);
        $this->flush();

        $log = $this->findLog();

        $this->assertNotNull($log, 'afterSaveフックがログを書いている');
        $this->assertSame('unit_knight_001', $log->mst_unit_id, '文字列のマスターIDがそのまま入る');
        $this->assertSame($unit->getId(), (int) $log->trx_unit_id);
        $this->assertSame(3, (int) $log->before_level);
        $this->assertSame(4, (int) $log->after_level);
        $this->assertSame(100, (int) $log->before_level_exp);
        $this->assertSame(250, (int) $log->after_level_exp);
        $this->assertSame('request-unit-1', $log->unique_request_id);
    }

    #[Test]
    public function グレードアップもログに残る(): void
    {
        $unit = $this->makeUnit('unit_mage_002', grade: 1);

        $unit->setGrade(2);
        $this->setModel($unit);
        $this->flush();

        $log = $this->findLog();

        $this->assertNotNull($log);
        $this->assertSame(1, (int) $log->before_grade);
        $this->assertSame(2, (int) $log->after_grade);
    }

    #[Test]
    public function 数字だけのマスターidも文字列のまま記録する(): void
    {
        // mst_unit_id は varchar なので、数値に見えるIDでも桁落ちさせない
        $unit = $this->makeUnit('0012');

        $unit->setLevel(2);
        $this->setModel($unit);
        $this->flush();

        $log = $this->findLog();

        $this->assertNotNull($log);
        $this->assertSame('0012', $log->mst_unit_id);
    }

    private function makeUnit(string $mstUnitId, int $grade = 1, int $level = 1, int $levelExp = 0): TrxUnit
    {
        return _BaseModel::allowDirectWrites(function () use ($mstUnitId, $grade, $level, $levelExp) {
            $unit = new TrxUnit([
                'sys_player_id' => $this->sysPlayerId,
                'mst_unit_id' => $mstUnitId,
                'grade' => $grade,
                'level' => $level,
                'level_exp' => $levelExp,
                'is_delete' => false,
            ]);
            $unit->setConnection('trx1');
            $unit->save();

            return $unit;
        });
    }

    private function setModel(TrxUnit $unit): void
    {
        $repository = $this->trxUnitRepository;

        $method = new \ReflectionMethod($repository, 'setModel');
        $method->setAccessible(true);
        $method->invoke($repository, $unit);
    }

    private function flush(): void
    {
        $this->queryManager->execAllQuery();
        $this->queryManager->execAllLogs();
    }

    private function findLog(): ?object
    {
        foreach (['log1', 'log2', 'log3'] as $connection) {
            $log = DB::connection($connection)->table('log_unit')
                ->where('sys_player_id', $this->sysPlayerId)->first();

            if ($log !== null) {
                return $log;
            }
        }

        return null;
    }
}
