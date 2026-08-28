<?php

namespace Tests\Feature\Tidb;

use Illuminate\Support\Facades\DB;
use NexusTidb\Support\TidbMode;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * TiDB用マイグレーションのテスト
 *
 * DB_IS_TIDB=true のときだけ id を UUID が入る型へ変える。
 * MySQLで動かしている間は何もしないことが要点。
 */
class UuidMigrationTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const TRX_MIGRATION = __DIR__.'/../../../../packages/nexus-tidb/database/migrations/trx/2026_08_28_000001_convert_trx_ids_to_uuid.php';

    private const LOG_MIGRATION = __DIR__.'/../../../../packages/nexus-tidb/database/migrations/log/2026_08_28_000001_convert_log_ids_to_uuid.php';

    protected function tearDown(): void
    {
        TidbMode::resetForTest();

        parent::tearDown();
    }

    #[Test]
    public function tidbでなければ何も変えない(): void
    {
        TidbMode::fakeForTest(false);

        $this->runMigration(self::TRX_MIGRATION, 'trx1', 'up');

        $this->assertSame('bigint unsigned', $this->columnType('trx1', 'trx_unit', 'id'));
        $this->assertSame('auto_increment', $this->columnExtra('trx1', 'trx_unit', 'id'));
    }

    #[Test]
    public function tidbならtrxのidがuuid用の型になる(): void
    {
        TidbMode::fakeForTest(true);

        try {
            $this->runMigration(self::TRX_MIGRATION, 'trx1', 'up');

            $this->assertSame('varchar(36)', $this->columnType('trx1', 'trx_unit', 'id'));
            $this->assertSame('', $this->columnExtra('trx1', 'trx_unit', 'id'), 'AUTO_INCREMENTが外れている');

            // 複合主キーのテーブルは対象外
            $this->assertSame('bigint unsigned', $this->columnType('trx1', 'trx_stamina', 'sys_player_id'));
        } finally {
            $this->runMigration(self::TRX_MIGRATION, 'trx1', 'down');
        }
    }

    #[Test]
    public function tidbならlogのidと参照列も変わる(): void
    {
        TidbMode::fakeForTest(true);

        try {
            $this->runMigration(self::LOG_MIGRATION, 'log1', 'up');

            $this->assertSame('varchar(36)', $this->columnType('log1', 'log_unit', 'id'));
            // TrxDBのidを控えている列。BIGINTのままだとUUIDが壊れる
            $this->assertSame('varchar(36)', $this->columnType('log1', 'log_unit', 'trx_unit_id'));
        } finally {
            $this->runMigration(self::LOG_MIGRATION, 'log1', 'down');
        }
    }

    #[Test]
    public function カラムコメントは保たれる(): void
    {
        TidbMode::fakeForTest(true);

        $before = $this->columnComment('log1', 'log_unit', 'id');
        $this->assertNotSame('', $before, '元からコメントがある前提');

        try {
            $this->runMigration(self::LOG_MIGRATION, 'log1', 'up');

            $this->assertSame($before, $this->columnComment('log1', 'log_unit', 'id'));
        } finally {
            $this->runMigration(self::LOG_MIGRATION, 'log1', 'down');
        }
    }

    #[Test]
    public function downで元のauto_incrementに戻る(): void
    {
        TidbMode::fakeForTest(true);

        $this->runMigration(self::TRX_MIGRATION, 'trx1', 'up');
        $this->runMigration(self::TRX_MIGRATION, 'trx1', 'down');

        $this->assertSame('bigint unsigned', $this->columnType('trx1', 'trx_unit', 'id'));
        $this->assertSame('auto_increment', $this->columnExtra('trx1', 'trx_unit', 'id'));
    }

    private function runMigration(string $path, string $connection, string $method): void
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            $migration = require $path;
            $migration->{$method}();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    private function columnType(string $connection, string $table, string $column): string
    {
        return (string) $this->columnInfo($connection, $table, $column)->COLUMN_TYPE;
    }

    private function columnExtra(string $connection, string $table, string $column): string
    {
        return (string) $this->columnInfo($connection, $table, $column)->EXTRA;
    }

    private function columnComment(string $connection, string $table, string $column): string
    {
        return (string) $this->columnInfo($connection, $table, $column)->COLUMN_COMMENT;
    }

    private function columnInfo(string $connection, string $table, string $column): object
    {
        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_TYPE, EXTRA, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        $this->assertNotEmpty($rows, "{$connection}.{$table}.{$column} が見つからない");

        return $rows[0];
    }
}
