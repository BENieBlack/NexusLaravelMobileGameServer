<?php

namespace Tests\Feature\Mailbox;

use App\Console\Commands\Mailbox\DeleteExpiredCommand;
use App\Domain\Mailbox\UseCases\LockUseCase;
use App\Exceptions\GameException;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * trx_mailbox の行ロックのテスト
 *
 * is_protected / is_delete は、本人の API（LockUseCase）とバッチ（mailbox:delete-expired）が書く。
 * 書く側が全員 FOR UPDATE で読み直すことと、
 * バッチが「読んだ後に保護された行」を消さないことを確かめる。
 */
class MailboxRowLockTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    public function beginDatabaseTransaction(): void
    {
        // UseCase / コマンドが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;

        ApiSession::setSysPlayerId($this->sysPlayerId);
        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        foreach (['trx1', 'trx2'] as $connection) {
            DB::connection($connection)->table('trx_mailbox')
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }
        ApiSession::clearForTest();
        ClockUtility::reset();
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function バッチは対象行をトランザクション内でfor_updateで読む(): void
    {
        $expired = $this->makeMailbox('2026-03-15 11:59:59');

        $lockedReads = [];
        DB::listen(function (QueryExecuted $query) use (&$lockedReads) {
            if ($this->isMailboxForUpdateSelect($query->sql)) {
                // ロックはトランザクション内でしか効かないので、読んだ時点のレベルも記録する
                $lockedReads[] = $query->connection->transactionLevel();
            }
        });

        $this->artisan('mailbox:delete-expired')->assertExitCode(0);

        $this->assertCount(1, $lockedReads);
        $this->assertGreaterThan(0, $lockedReads[0]);
        $this->assertTrue((bool) $this->findRow($expired->id)->is_delete);
    }

    #[Test]
    public function バッチが一覧を読んだ後に保護された行は削除しない(): void
    {
        $raced = $this->makeMailbox('2026-03-15 11:59:59');
        $plain = $this->makeMailbox('2026-03-15 11:59:59');

        // 対象シャードの候補一覧（ロックなしの SELECT）を読んだ直後に、プレイヤーが保護した状況を作る
        $fired = false;
        DB::listen(function (QueryExecuted $query) use (&$fired, $raced) {
            if ($fired || $query->connectionName !== $this->shard() || ! $this->isMailboxPlainSelect($query->sql)) {
                return;
            }
            $fired = true;
            DB::connection($this->shard())->table('trx_mailbox')->where('id', $raced->id)->update(['is_protected' => true]);
        });

        $this->artisan('mailbox:delete-expired')
            ->expectsOutputToContain('スキップ: 1 件')
            ->assertExitCode(0);

        $this->assertTrue($fired);
        $this->assertFalse((bool) $this->findRow($raced->id)->is_delete, '保護済みの行を消してはいけない');
        $this->assertTrue((bool) $this->findRow($raced->id)->is_protected);
        $this->assertTrue((bool) $this->findRow($plain->id)->is_delete);
    }

    #[Test]
    public function バッチが一覧を読んだ後に期限が延びた行は削除しない(): void
    {
        $extended = $this->makeMailbox('2026-03-15 11:59:59');

        $fired = false;
        DB::listen(function (QueryExecuted $query) use (&$fired, $extended) {
            if ($fired || $query->connectionName !== $this->shard() || ! $this->isMailboxPlainSelect($query->sql)) {
                return;
            }
            $fired = true;
            DB::connection($this->shard())->table('trx_mailbox')->where('id', $extended->id)
                ->update(['expires_at' => '2026-04-01 00:00:00']);
        });

        $this->artisan('mailbox:delete-expired')->assertExitCode(0);

        $this->assertFalse((bool) $this->findRow($extended->id)->is_delete);
    }

    #[Test]
    public function 再判定は保護済み_削除済み_期限なし_期限内を対象外にする(): void
    {
        $command = DeleteExpiredCommand::class;

        $this->assertTrue($command::isDeletable($this->makeMailbox('2026-03-15 11:59:59')));
        $this->assertFalse($command::isDeletable($this->makeMailbox('2026-03-15 11:59:59', isProtected: true)));
        $this->assertFalse($command::isDeletable($this->makeMailbox('2026-03-15 12:00:01')));
        $this->assertFalse($command::isDeletable($this->makeMailbox(null)));

        $deleted = $this->makeMailbox('2026-03-15 11:59:59');
        _BaseModel::allowDirectWrites(function () use ($deleted) {
            $deleted->setIsDelete(true);
            $deleted->save();
        });
        $this->assertFalse($command::isDeletable($deleted));
    }

    #[Test]
    public function lock_use_caseはトランザクション内でfor_updateして保護を更新する(): void
    {
        $mailbox = $this->makeMailbox('2026-03-15 11:59:59');

        $lockedReads = [];
        DB::listen(function (QueryExecuted $query) use (&$lockedReads) {
            if ($this->isMailboxForUpdateSelect($query->sql)) {
                $lockedReads[] = $query->connection->transactionLevel();
            }
        });

        app(LockUseCase::class)->handle($this->sysPlayerId, $mailbox->id, true);

        $this->assertCount(1, $lockedReads);
        $this->assertGreaterThan(0, $lockedReads[0]);
        $this->assertTrue((bool) $this->findRow($mailbox->id)->is_protected);
    }

    #[Test]
    public function lock_use_caseはロック読みで削除済みと分かった行を保護しない(): void
    {
        // バッチが先に is_delete を付けた後に保護しようとした場合、最新の値で拒否される
        $mailbox = $this->makeMailbox('2026-03-15 11:59:59');
        DB::connection($this->shard())->table('trx_mailbox')->where('id', $mailbox->id)->update(['is_delete' => true]);

        $this->expectException(GameException::class);

        try {
            app(LockUseCase::class)->handle($this->sysPlayerId, $mailbox->id, true);
        } finally {
            $this->assertFalse((bool) $this->findRow($mailbox->id)->is_protected);
        }
    }

    #[Test]
    public function trx2のシャードの行もそのシャードの接続でロックして削除する(): void
    {
        $mailbox = $this->makeMailbox('2026-03-15 11:59:59', connection: 'trx2');

        $connections = [];
        DB::listen(function (QueryExecuted $query) use (&$connections) {
            if ($this->isMailboxForUpdateSelect($query->sql)) {
                $connections[] = $query->connectionName;
            }
        });

        $this->artisan('mailbox:delete-expired')->assertExitCode(0);

        // 行のあるシャードの接続でロックを取り、その接続へ書く
        $this->assertSame(['trx2'], $connections);
        $this->assertTrue((bool) DB::connection('trx2')->table('trx_mailbox')->where('id', $mailbox->id)->first()->is_delete);
    }

    /** trx_mailbox への FOR UPDATE 付き SELECT か */
    private function isMailboxForUpdateSelect(string $sql): bool
    {
        $sql = strtolower($sql);

        return str_starts_with($sql, 'select') && str_contains($sql, 'trx_mailbox') && str_contains($sql, 'for update');
    }

    /** trx_mailbox へのロックなしの SELECT か（バッチの候補一覧） */
    private function isMailboxPlainSelect(string $sql): bool
    {
        $sql = strtolower($sql);

        return str_starts_with($sql, 'select') && str_contains($sql, 'trx_mailbox') && ! str_contains($sql, 'for update');
    }

    private function makeMailbox(?string $expiresAt, bool $isProtected = false, ?string $connection = null): TrxMailbox
    {
        return _BaseModel::allowDirectWrites(function () use ($expiresAt, $isProtected, $connection) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $this->sysPlayerId,
                'mst_mailbox_id' => 'mailbox_test_001',
                'is_opened' => false,
                'is_received' => false,
                'is_protected' => $isProtected,
                'expires_at' => $expiresAt,
            ]);
            $mailbox->setConnection($connection ?? $this->shard());
            $mailbox->save();

            return $mailbox;
        });
    }

    /** テストのプレイヤーが割り当てられているシャード接続（バッチ・UseCase が見る接続） */
    private function shard(): string
    {
        return ApiSession::resolveConnectionName('trx');
    }

    private function findRow(int $id): ?object
    {
        return DB::connection($this->shard())->table('trx_mailbox')->where('id', $id)->first();
    }
}
