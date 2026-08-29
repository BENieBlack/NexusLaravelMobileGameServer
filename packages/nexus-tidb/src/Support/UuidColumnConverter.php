<?php

namespace NexusTidb\Support;

use Illuminate\Support\Facades\DB;

/**
 * UuidColumnConverter
 *
 * 主キー id と、それを参照する列を UUID が入る型へ入れ替える
 *
 * 既存のマイグレーションは AUTO_INCREMENT のまま置いておき、
 * TiDB利用時だけこの変換を後から流す。
 */
final class UuidColumnConverter
{
    /**
     * UUIDを入れる列の型（UUIDは36文字固定）
     */
    private const UUID_COLUMN_TYPE = 'VARCHAR(36)';

    /**
     * 変換前の型（元に戻すとき用）
     */
    private const AUTO_INCREMENT_COLUMN_TYPE = 'BIGINT UNSIGNED';

    /**
     * Laravel自身が作るテーブル（アプリのデータではないので対象外）
     */
    private const LARAVEL_TABLES = ['migrations', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'];

    /**
     * 接続内の「単一主キー id が AUTO_INCREMENT」のテーブル名を返す
     *
     * @return list<string>
     */
    public static function findAutoIncrementIdTables(string $connection): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT c.TABLE_NAME AS table_name
             FROM INFORMATION_SCHEMA.COLUMNS c
             WHERE c.TABLE_SCHEMA = DATABASE()
               AND c.COLUMN_NAME = ?
               AND c.EXTRA = ?
             ORDER BY c.TABLE_NAME',
            ['id', 'auto_increment']
        );

        $tables = [];

        foreach ($rows as $row) {
            $tableName = (string) $row->table_name;

            if (in_array($tableName, self::LARAVEL_TABLES, true)) {
                continue;
            }

            $tables[] = $tableName;
        }

        return $tables;
    }

    /**
     * 接続内の「主キーが id 単独」のテーブル名を返す
     *
     * 変換の前後どちらでも同じ結果になるよう、AUTO_INCREMENTではなく
     * 主キーの構成で判定する。
     *
     * @return list<string>
     */
    public static function findSingleIdPrimaryKeyTables(string $connection): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT TABLE_NAME AS table_name
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME = ?
             GROUP BY TABLE_NAME
             HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = ?
             ORDER BY TABLE_NAME',
            ['PRIMARY', 'id']
        );

        $tables = [];

        foreach ($rows as $row) {
            $tableName = (string) $row->table_name;

            if (in_array($tableName, self::LARAVEL_TABLES, true)) {
                continue;
            }

            $tables[] = $tableName;
        }

        return $tables;
    }

    /**
     * 他テーブルのidを指す列を探す
     *
     * 列名の規約（参照先のテーブル名 + _id）から参照先を割り出し、
     * その参照先がUUID化される場合だけ対象にする。
     * 一覧を手で持たないので、テーブルが増えても取りこぼさない。
     *
     * @param  list<string>  $uuidTables  UUID化される参照先テーブル
     * @return list<array{table: string, column: string, isNullable: bool}>
     */
    public static function findReferenceColumns(string $connection, array $uuidTables): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, IS_NULLABLE AS is_nullable
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE ? AND COLUMN_NAME <> ?
             ORDER BY TABLE_NAME, COLUMN_NAME',
            ['%\_id', 'id']
        );

        $references = [];

        foreach ($rows as $row) {
            $columnName = (string) $row->column_name;

            // 参照先のテーブル名は列名から _id を落としたもの
            $referencedTable = substr($columnName, 0, -3);

            if (! in_array($referencedTable, $uuidTables, true)) {
                continue;
            }

            $references[] = [
                'table' => (string) $row->table_name,
                'column' => $columnName,
                'isNullable' => $row->is_nullable === 'YES',
            ];
        }

        return $references;
    }

    /**
     * id を UUID が入る型へ変える
     */
    public static function toUuid(string $connection, string $table, string $column = 'id'): void
    {
        self::modify($connection, $table, $column, self::UUID_COLUMN_TYPE.' NOT NULL');
    }

    /**
     * id を AUTO_INCREMENT へ戻す
     */
    public static function toAutoIncrement(string $connection, string $table, string $column = 'id'): void
    {
        self::modify($connection, $table, $column, self::AUTO_INCREMENT_COLUMN_TYPE.' NOT NULL AUTO_INCREMENT');
    }

    /**
     * 他テーブルのidを指す列をUUIDが入る型へ変える
     *
     * 参照先がUUIDになるのに参照元がBIGINTのままだと、
     * 値が黙って壊れるため合わせて変換する。
     */
    public static function referenceToUuid(string $connection, string $table, string $column, bool $isNullable): void
    {
        self::modify($connection, $table, $column, self::UUID_COLUMN_TYPE.($isNullable ? ' NULL' : ' NOT NULL'));
    }

    /**
     * 参照列をBIGINTへ戻す
     */
    public static function referenceToBigInt(string $connection, string $table, string $column, bool $isNullable): void
    {
        self::modify($connection, $table, $column, self::AUTO_INCREMENT_COLUMN_TYPE.($isNullable ? ' NULL' : ' NOT NULL'));
    }

    /**
     * 列が存在する場合だけ型を変える
     */
    private static function modify(string $connection, string $table, string $column, string $definition): void
    {
        if (! self::hasColumn($connection, $table, $column)) {
            return;
        }

        $comment = self::findColumnComment($connection, $table, $column);
        $commentClause = $comment === '' ? '' : " COMMENT '".str_replace("'", "''", $comment)."'";

        DB::connection($connection)->statement(
            "ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}{$commentClause}"
        );
    }

    private static function hasColumn(string $connection, string $table, string $column): bool
    {
        $rows = DB::connection($connection)->select(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $rows !== [];
    }

    /**
     * 既存のカラムコメントを引き継ぐ（MODIFYは指定しないと消える）
     */
    private static function findColumnComment(string $connection, string $table, string $column): string
    {
        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_COMMENT AS column_comment FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $rows === [] ? '' : (string) $rows[0]->column_comment;
    }
}
