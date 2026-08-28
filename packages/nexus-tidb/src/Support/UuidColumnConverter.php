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

            // Laravel自身のテーブルは対象外
            if (in_array($tableName, ['migrations', 'jobs', 'job_batches', 'failed_jobs'], true)) {
                continue;
            }

            $tables[] = $tableName;
        }

        return $tables;
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
