<?php

namespace Tests\Feature\Tidb;

use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxMailboxRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nexus\Core\Utilities\ClockUtility;
use NexusTidb\Support\TidbMode;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * TiDB利用時のINSERT経路のテスト
 *
 * UnitOfWorkはEloquentを介さず直接INSERTし、そのあと
 * LAST_INSERT_ID() でモデルにIDを書き戻す。
 * UUIDを使う場合はこの書き戻しが起きず、生成済みのUUIDが
 * そのまま行のIDになることを確認する。
 *
 * テストDBはMySQLでidがBIGINTのため、UUIDを入れられるよう
 * このテストの中だけ列をvarcharに作り替える。
 */
class UuidPrimaryKeyInsertTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $connection;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-08-28 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $this->connection = $this->playerConnection($this->sysPlayerId);

        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        TidbMode::resetForTest();
        $this->restoreAutoIncrementId();

        DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->delete();

        ApiSession::clearForTest();
        ClockUtility::reset();
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function tidb利用時はuuidがそのまま行のidになる(): void
    {
        $this->makeIdColumnString();
        TidbMode::fakeForTest(true);

        $repository = app(TrxMailboxRepository::class);
        $mailbox = new TrxMailbox([
            'sys_player_id' => $this->sysPlayerId,
            'mst_mailbox_id' => 'mailbox_test_001',
            'is_opened' => false,
            'is_received' => false,
        ]);
        $mailbox->exists = false;

        $generatedId = $mailbox->getAttribute('id');
        $this->assertIsString($generatedId, '生成時点でUUIDが入っている');

        $this->setModel($repository, $mailbox);
        app(QueryManager::class)->flush();

        $row = DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->first();

        $this->assertNotNull($row);
        $this->assertSame($generatedId, $row->id, 'LAST_INSERT_ID()で上書きされていない');
        $this->assertSame($generatedId, $mailbox->getAttribute('id'), 'モデル側も変わっていない');
    }

    #[Test]
    public function tidb利用時は複数件でもuuidが衝突しない(): void
    {
        $this->makeIdColumnString();
        TidbMode::fakeForTest(true);

        $repository = app(TrxMailboxRepository::class);
        $expectedIds = [];

        for ($i = 0; $i < 3; $i++) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $this->sysPlayerId,
                'mst_mailbox_id' => "mailbox_test_00{$i}",
                'is_opened' => false,
                'is_received' => false,
            ]);
            $mailbox->exists = false;
            $expectedIds[] = $mailbox->getAttribute('id');
            $this->setModel($repository, $mailbox);
        }

        app(QueryManager::class)->flush();

        $actualIds = DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->pluck('id')->all();

        sort($expectedIds);
        sort($actualIds);

        $this->assertSame($expectedIds, $actualIds);
    }

    #[Test]
    public function tidbでなければ従来どおり採番される(): void
    {
        TidbMode::fakeForTest(false);

        $repository = app(TrxMailboxRepository::class);
        $mailbox = new TrxMailbox([
            'sys_player_id' => $this->sysPlayerId,
            'mst_mailbox_id' => 'mailbox_test_001',
            'is_opened' => false,
            'is_received' => false,
        ]);
        $mailbox->exists = false;

        $this->assertNull($mailbox->getAttribute('id'), 'AUTO_INCREMENTに任せる');

        $this->setModel($repository, $mailbox);
        app(QueryManager::class)->flush();

        $row = DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->first();

        $this->assertNotNull($row);
        $this->assertGreaterThan(0, (int) $row->id);
        $this->assertSame((int) $row->id, (int) $mailbox->getAttribute('id'), 'モデルへIDが書き戻される');
    }

    private function setModel(TrxMailboxRepository $repository, TrxMailbox $mailbox): void
    {
        $method = new \ReflectionMethod($repository, 'setModel');
        $method->setAccessible(true);
        $method->invoke($repository, $mailbox);
    }

    /**
     * trx_mailbox.id をUUIDが入る型に作り替える
     */
    private function makeIdColumnString(): void
    {
        DB::connection($this->connection)->statement(
            'ALTER TABLE trx_mailbox MODIFY id VARCHAR(36) NOT NULL'
        );
    }

    /**
     * id を元のAUTO_INCREMENTへ戻す
     */
    private function restoreAutoIncrementId(): void
    {
        $column = Schema::connection($this->connection)->getColumnType('trx_mailbox', 'id');

        if ($column === 'bigint') {
            return;
        }

        DB::connection($this->connection)->table('trx_mailbox')->delete();
        DB::connection($this->connection)->statement(
            'ALTER TABLE trx_mailbox MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );
    }
}
