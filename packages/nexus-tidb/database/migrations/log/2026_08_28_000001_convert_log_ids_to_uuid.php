<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use NexusTidb\Support\TidbMode;
use NexusTidb\Support\UuidColumnConverter;

/**
 * LogDBの単一主キー id と、TrxDBのidを指す列を UUID が入る型へ変える
 *
 * DB_IS_TIDB=true のときだけ実行される。MySQLで動かす場合は何もしない。
 *
 * ログ側にはTrxDBのidを控えている列がある。参照先がUUIDになるのに
 * ここがBIGINTのままだと値が黙って壊れるため、合わせて変換する。
 */
return new class extends Migration
{
    /**
     * TrxDBのidを指す列（テーブル => [列名 => NULL許容か]）
     *
     * マスターのIDを指す列（mst_in_app_purchase_id など）は
     * UUIDにならないので含めない。
     *
     * @var array<string, array<string, bool>>
     */
    private const TRX_ID_REFERENCES = [
        'log_equipment' => ['trx_equipment_id' => false],
        'log_unit' => ['trx_unit_id' => false],
        'log_trx_diamond_balance' => ['trx_diamond_balance_id' => true],
        'log_trx_equipment' => ['trx_equipment_id' => true],
        'log_trx_gacha' => ['trx_gacha_id' => true],
        'log_trx_in_app_purchase_effect' => ['trx_in_app_purchase_effect_id' => true],
        'log_trx_login_bonus_history' => ['trx_login_bonus_history_id' => true],
        'log_trx_mailbox' => ['trx_mailbox_id' => true],
        'log_trx_unit' => ['trx_unit_id' => true],
        'log_trx_vip_login_bonus_history' => ['trx_vip_login_bonus_history_id' => true],
        'log_trx_wallet_balance' => ['trx_wallet_balance_id' => true],
    ];

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

        foreach (self::TRX_ID_REFERENCES as $table => $columns) {
            foreach ($columns as $column => $isNullable) {
                UuidColumnConverter::referenceToUuid($connection, $table, $column, $isNullable);
            }
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

        foreach (self::TRX_ID_REFERENCES as $table => $columns) {
            foreach ($columns as $column => $isNullable) {
                UuidColumnConverter::referenceToBigInt($connection, $table, $column, $isNullable);
            }
        }

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
