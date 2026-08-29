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
 * 値の生成は NexusTidb\Traits\UuidPrimaryKey が行う。
 *
 * 対象は列名の規約（参照先のテーブル名 + _id）から割り出すので、
 * テーブルが増えても一覧を直す必要はない。
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

        // trx同士でidを指し合う列があれば合わせて変換する
        foreach ($this->findTrxIdReferences($connection) as $reference) {
            UuidColumnConverter::referenceToUuid(
                $connection,
                $reference['table'],
                $reference['column'],
                $reference['isNullable'],
            );
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

        foreach ($this->findTrxIdReferences($connection) as $reference) {
            UuidColumnConverter::referenceToBigInt(
                $connection,
                $reference['table'],
                $reference['column'],
                $reference['isNullable'],
            );
        }

        foreach ($this->convertedTables($connection) as $table) {
            UuidColumnConverter::toAutoIncrement($connection, $table);
        }
    }

    /**
     * 同じTrxDB内でidを指している列を割り出す
     *
     * @return list<array{table: string, column: string, isNullable: bool}>
     */
    private function findTrxIdReferences(string $connection): array
    {
        return UuidColumnConverter::findReferenceColumns(
            $connection,
            UuidColumnConverter::findSingleIdPrimaryKeyTables($connection),
        );
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
