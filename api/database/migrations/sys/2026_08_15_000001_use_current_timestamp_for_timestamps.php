<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * created_at / updated_at をDB側の既定値に移行する
 *
 * アプリケーション側でタイムスタンプを設定するのをやめ、
 * DEFAULT CURRENT_TIMESTAMP / ON UPDATE CURRENT_TIMESTAMP に一本化する。
 *
 * 新規に作られたテーブルは各パッケージのマイグレーションで既にこの定義になっている。
 * このマイグレーションは、それ以前に作られた既存環境のテーブルを揃えるためのもの。
 */
return new class extends Migration
{
    /**
     * 対象の接続名
     *
     * @return list<string>
     */
    private function connections(): array
    {
        $connections = ['sys', 'mst'];

        $shardCount = (int) env('DB_SHARD_COUNT', 2);
        for ($i = 1; $i <= $shardCount; $i++) {
            $connections[] = "trx{$i}";
            $connections[] = "log{$i}";
        }

        return $connections;
    }

    public function up(): void
    {
        foreach ($this->connections() as $connection) {
            $database = config("database.connections.{$connection}.database");

            if ($database === null) {
                continue;
            }

            // 既定値が未設定の created_at / updated_at を持つテーブルを洗い出す
            $columns = DB::connection($connection)->select(
                'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND COLUMN_NAME IN (?, ?)
                   AND COLUMN_DEFAULT IS NULL
                   AND DATA_TYPE = ?',
                [$database, 'created_at', 'updated_at', 'datetime']
            );

            foreach ($columns as $column) {
                $default = $column->COLUMN_NAME === 'updated_at'
                    ? 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
                    : 'DEFAULT CURRENT_TIMESTAMP';

                // 既存のNULL行を埋めてからNOT NULLへ変更する
                DB::connection($connection)->statement(sprintf(
                    'UPDATE `%s` SET `%s` = CURRENT_TIMESTAMP WHERE `%s` IS NULL',
                    $column->TABLE_NAME,
                    $column->COLUMN_NAME,
                    $column->COLUMN_NAME
                ));

                DB::connection($connection)->statement(sprintf(
                    'ALTER TABLE `%s` MODIFY `%s` DATETIME NOT NULL %s',
                    $column->TABLE_NAME,
                    $column->COLUMN_NAME,
                    $default
                ));
            }
        }
    }

    public function down(): void
    {
        // 既定値を外すだけの巻き戻しは行わない
        // （アプリケーション側がタイムスタンプを設定しなくなっているため）
    }
};
