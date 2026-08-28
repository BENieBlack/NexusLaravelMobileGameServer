<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use NexusTidb\Support\TidbMode;
use NexusTidb\Support\UuidColumnConverter;

/**
 * TrxDBの単一主キー id を UUID が入る型へ変える
 *
 * DB_IS_TIDB=true のときだけ実行される。MySQLで動かす場合は何もしない。
 *
 * TiDBは AUTO_INCREMENT の単調増加を保証せず、連番キーは書き込みが
 * 特定リージョンへ集中する原因にもなるため、idをUUIDで払い出す。
 * 値の生成は NexusTidb\Concerns\UsesUuidPrimaryKey が行う。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! TidbMode::isEnabled()) {
            return;
        }

        // migrate --database=trx2 のように対象が切り替わるため、
        // 実行時の既定接続を使う（固定するとシャードを取り違える）
        $connection = $this->getConnection() ?? DB::getDefaultConnection();

        foreach (UuidColumnConverter::findAutoIncrementIdTables($connection) as $table) {
            UuidColumnConverter::toUuid($connection, $table);
        }
    }

    public function down(): void
    {
        if (! TidbMode::isEnabled()) {
            return;
        }

        // migrate --database=trx2 のように対象が切り替わるため、
        // 実行時の既定接続を使う（固定するとシャードを取り違える）
        $connection = $this->getConnection() ?? DB::getDefaultConnection();

        foreach ($this->convertedTables($connection) as $table) {
            UuidColumnConverter::toAutoIncrement($connection, $table);
        }
    }

    /**
     * up()で変換したテーブル（idがVARCHARになっているもの）
     *
     * @return list<string>
     */
    private function convertedTables(string $connection): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ? AND DATA_TYPE = ?
             ORDER BY TABLE_NAME',
            ['id', 'varchar']
        );

        return array_map(fn (object $row) => (string) $row->table_name, $rows);
    }
};
