<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use NexusPitr\Logger\ShardMapper;
use NexusTidb\Support\TidbMode;
use NexusTidb\Support\UuidColumnConverter;

/**
 * LogDBの単一主キー id と、TrxDBのidを指す列を UUID が入る型へ変える
 *
 * DB_IS_TIDB=true のときだけ実行される。MySQLで動かす場合は何もしない。
 *
 * ログ側にはTrxDBのidを控えている列（trx_mailbox_id など）がある。
 * 参照先がUUIDになるのにここがBIGINTのままだと値が黙って壊れるため、
 * 合わせて変換する。
 *
 * 対象は列名の規約（参照先のテーブル名 + _id）から割り出すので、
 * ログテーブルが増えても一覧を直す必要はない。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! TidbMode::isEnabled()) {
            return;
        }

        // migrate --database=log2 のように対象が切り替わるため、
        // 実行時の既定接続を使う（固定するとシャードを取り違える）
        $connection = $this->getConnection() ?? DB::getDefaultConnection();

        foreach (UuidColumnConverter::findAutoIncrementIdTables($connection) as $table) {
            UuidColumnConverter::toUuid($connection, $table);
        }

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
     * TrxDBのidを指す列を割り出す
     *
     * 対になるTrxDB（log2 なら trx2）で「主キーが id 単独」のテーブルを調べ、
     * その名前 + _id の列をログ側から探す。
     *
     * @return list<array{table: string, column: string, isNullable: bool}>
     */
    private function findTrxIdReferences(string $logConnection): array
    {
        $trxConnection = ShardMapper::resolveTrxConnection($logConnection);
        $uuidTables = UuidColumnConverter::findSingleIdPrimaryKeyTables($trxConnection);

        return UuidColumnConverter::findReferenceColumns($logConnection, $uuidTables);
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
