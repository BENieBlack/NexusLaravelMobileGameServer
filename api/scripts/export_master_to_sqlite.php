#!/usr/bin/env php
<?php

/**
 * マスターデータベースをSQLiteに変換してエクスポートするスクリプト
 *
 * 使用方法:
 *   php scripts/export_master_to_sqlite.php
 *
 * 出力先:
 *   storage/app/master.db
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Laravelアプリケーションをブートストラップ
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// 出力先SQLiteファイルパス
$sqlitePath = storage_path('app/master.db');

// 既存のSQLiteファイルを削除
if (file_exists($sqlitePath)) {
    unlink($sqlitePath);
    echo "既存のmaster.dbを削除しました\n";
}

// SQLiteデータベースを作成
$sqlite = new PDO('sqlite:'.$sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "SQLiteデータベースを作成: {$sqlitePath}\n";

// マスターデータベース接続
$mstConnection = 'mst';

// エクスポート対象テーブル
$tables = [
    'mst_gacha',
    'mst_gacha__l10n',
    'mst_unit',
    'mst_unit__l10n',
    'mst_equipment',
    'mst_equipment__l10n',
];

foreach ($tables as $table) {
    echo "\n[{$table}] エクスポート中...\n";

    // テーブルスキーマを取得
    $columns = Schema::connection($mstConnection)->getColumnListing($table);

    if (empty($columns)) {
        echo "  ⚠️ テーブルが存在しないか、カラムが空です: {$table}\n";

        continue;
    }

    // CREATE TABLE文を生成（簡易版）
    $createTableSql = generateCreateTableSQL($table, $columns, $mstConnection);
    $sqlite->exec($createTableSql);
    echo "  ✓ テーブル作成完了\n";

    // データを取得
    $records = DB::connection($mstConnection)->table($table)->get();

    if ($records->isEmpty()) {
        echo "  ℹ️ データが空です\n";

        continue;
    }

    // データを挿入
    $sqlite->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $columnsList = implode(',', array_map(fn ($col) => "`{$col}`", $columns));
    $insertSql = "INSERT INTO `{$table}` ({$columnsList}) VALUES ({$placeholders})";

    $stmt = $sqlite->prepare($insertSql);

    $count = 0;
    foreach ($records as $record) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = $record->{$column};
        }
        $stmt->execute($values);
        $count++;
    }

    $sqlite->commit();

    echo "  ✓ データ挿入完了: {$count}件\n";
}

echo "\n✅ エクスポート完了: {$sqlitePath}\n";
echo 'ファイルサイズ: '.formatBytes(filesize($sqlitePath))."\n";

/**
 * CREATE TABLE文を生成（簡易版）
 */
function generateCreateTableSQL(string $table, array $columns, string $connection): string
{
    $columnDefinitions = [];

    foreach ($columns as $column) {
        $type = Schema::connection($connection)->getColumnType($table, $column);

        // MySQLの型をSQLiteの型にマッピング
        $sqliteType = match ($type) {
            'bigint', 'integer', 'int', 'smallint', 'tinyint' => 'INTEGER',
            'float', 'double', 'decimal' => 'REAL',
            'text', 'longtext', 'mediumtext' => 'TEXT',
            'json' => 'TEXT',
            'datetime', 'timestamp' => 'TEXT',
            'boolean' => 'INTEGER',
            default => 'TEXT',
        };

        $columnDefinitions[] = "`{$column}` {$sqliteType}";
    }

    $columnDefs = implode(",\n  ", $columnDefinitions);

    // プライマリキーの検出（簡易版：idカラムがあればPKとする）
    if (in_array('id', $columns)) {
        $pk = ",\n  PRIMARY KEY (`id`)";
    } else {
        $pk = '';
    }

    return "CREATE TABLE `{$table}` (\n  {$columnDefs}{$pk}\n)";
}

/**
 * ファイルサイズをフォーマット
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision).' '.$units[$pow];
}
