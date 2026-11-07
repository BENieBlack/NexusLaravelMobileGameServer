<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\LoginBonusHistoryRepositoryAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Contracts\QueryManagerInterface;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * LoginBonusHistoryRepositoryAdapter のテスト
 *
 * trx_login_bonus はプレイヤー×タイプごとに1行の状態で、受け取るたびに上書きする。
 * 報酬ごとの記録は log_action_login_bonus_receive に1行ずつ残す。
 */
class LoginBonusHistoryRepositoryAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $connection;

    private LoginBonusHistoryRepositoryAdapter $repository;

    public function beginDatabaseTransaction(): void
    {
        // flush() と execAllLogs() で実際に書き込むため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->connection = $this->playerConnection($this->sysPlayerId);

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->repository = app(LoginBonusHistoryRepositoryAdapter::class);
    }

    protected function tearDown(): void
    {
        foreach (['trx_login_bonus', 'log_action_login_bonus_receive'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        ClockUtility::reset();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 受け取ると状態を上書きし報酬ごとのログを残す(): void
    {
        $this->receive('daily', 'daily_login', day: 1, receivedDate: '2026-03-14 00:00:00', rewardMstId: 'item_a');
        $this->receive('daily', 'daily_login', day: 2, receivedDate: '2026-03-15 00:00:00', rewardMstId: 'item_a');
        $this->receive('daily', 'daily_login', day: 2, receivedDate: '2026-03-15 00:00:00', rewardMstId: 'item_b');
        $this->flushWrites();

        $rows = DB::connection($this->connection)->table('trx_login_bonus')
            ->where('sys_player_id', $this->sysPlayerId)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows[0]->day);
        $this->assertSame('2026-03-15 00:00:00', $rows[0]->received_date);

        $logs = DB::connection($this->connection)->table('log_action_login_bonus_receive')
            ->where('sys_player_id', $this->sysPlayerId)->get();
        $this->assertCount(3, $logs);
    }

    #[Test]
    public function 通常の最新状態を取得できる(): void
    {
        $this->assertNull($this->repository->selectLatestByPlayer($this->sysPlayerId));

        $this->receive('daily', 'daily_login', day: 3, receivedDate: '2026-03-15 00:00:00');
        $this->receive('comeback', 'comeback_7', day: 0, receivedDate: '2026-03-15 00:00:00', absentDays: 10);

        $loginBonusHistory = $this->repository->selectLatestByPlayer($this->sysPlayerId);
        $this->assertSame('daily', $loginBonusHistory?->getType());
        $this->assertSame(3, $loginBonusHistory?->getDay());

        $comeback = $this->repository->selectFirstComebackByPlayerId($this->sysPlayerId);
        $this->assertSame('comeback_7', $comeback?->getMstLoginBonusId());
        $this->assertSame(10, $comeback?->getAbsentDays());
    }

    #[Test]
    public function 指定日時以降に受け取っていれば1を数える(): void
    {
        $this->assertSame(0, $this->repository->countUniqueDaysSince($this->sysPlayerId, '2026-03-01 00:00:00'));

        $this->receive('daily', 'daily_login', day: 1, receivedDate: '2026-03-15 00:00:00');

        $this->assertSame(1, $this->repository->countUniqueDaysSince($this->sysPlayerId, '2026-03-15 00:00:00'));
        $this->assertSame(0, $this->repository->countUniqueDaysSince($this->sysPlayerId, '2026-03-16 00:00:00'));
    }

    #[Test]
    public function カムバックの状態がボーナスidと受取日に一致するときだけ取得できる(): void
    {
        $this->receive('comeback', 'comeback_7', day: 0, receivedDate: '2026-03-15 00:00:00', absentDays: 10);

        $this->assertNotNull($this->repository->selectByPlayerAndBonusAndDate($this->sysPlayerId, 'comeback_7', '2026-03-15 00:00:00'));
        $this->assertNull($this->repository->selectByPlayerAndBonusAndDate($this->sysPlayerId, 'comeback_30', '2026-03-15 00:00:00'));
        $this->assertNull($this->repository->selectByPlayerAndBonusAndDate($this->sysPlayerId, 'comeback_7', '2026-03-14 00:00:00'));
    }

    private function receive(
        string $type,
        string $mstLoginBonusId,
        int $day,
        string $receivedDate,
        string $rewardMstId = 'item_a',
        ?int $absentDays = null,
    ): void {
        $this->repository->insertReceive(
            sysPlayerId: $this->sysPlayerId,
            type: $type,
            mstLoginBonusId: $mstLoginBonusId,
            day: $day,
            absentDays: $absentDays,
            receivedDate: $receivedDate,
            rewardType: 'item',
            rewardMstId: $rewardMstId,
            rewardAmount: 1,
            isPaid: false,
        );
    }

    /**
     * UseCase の終わりと同じく、trx は flush()、log はその後の execAllLogs() で書く
     */
    private function flushWrites(): void
    {
        $this->flushQueue();
        app(QueryManagerInterface::class)->execAllLogs();
    }
}
