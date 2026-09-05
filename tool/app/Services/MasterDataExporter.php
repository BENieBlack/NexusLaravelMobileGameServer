<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MasterDataExporter
{
    /**
     * SQLiteファイルの出力先ディレクトリ（public/masterdata/）
     */
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = public_path('masterdata');
    }

    /**
     * mstデータベースの全テーブルをSQLiteに書き出す
     *
     * @return array{
     *   file_path: string,   // フルパス
     *   file_name: string,   // ファイル名（master_{hash}.sqlite）
     *   hash: string,        // SHA-256ハッシュ
     *   file_size: int,      // バイト数
     *   table_count: int,    // テーブル数
     *   public_url: string,  // /masterdata/master_{hash}.sqlite
     * }
     */
    public function export(): array
    {
        // 出力ディレクトリを作成
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        // 一時ファイルを作成してSQLiteに書き出す
        $tmpPath = $this->outputDir . '/tmp_' . uniqid() . '.sqlite';

        try {
            $sqlite = new \PDO('sqlite:' . $tmpPath);
            $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // mst_で始まる全テーブルを取得
            $tables = DB::connection('mst')
                ->select("SHOW TABLES LIKE 'mst_%'");

            $tableNames = array_map(fn($r) => array_values((array)$r)[0], $tables);

            $sqlite->beginTransaction();

            foreach ($tableNames as $tableName) {
                // テーブル構造を取得してSQLiteに作成
                $columns = DB::connection('mst')->select("SHOW COLUMNS FROM `{$tableName}`");
                $createSql = $this->buildCreateTableSql($tableName, $columns);
                $sqlite->exec($createSql);

                // データを全件取得してINSERT
                $rows = DB::connection('mst')->table($tableName)->get();

                if ($rows->isNotEmpty()) {
                    $columnNames = array_keys((array) $rows->first());
                    $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
                    $cols = implode(', ', array_map(fn($c) => "`{$c}`", $columnNames));
                    $stmt = $sqlite->prepare("INSERT INTO `{$tableName}` ({$cols}) VALUES ({$placeholders})");

                    foreach ($rows as $row) {
                        $values = array_values((array) $row);
                        $stmt->execute($values);
                    }
                }
            }

            $sqlite->commit();
            unset($sqlite); // 接続を閉じる

            // ファイルのSHA-256ハッシュを計算
            $hash = hash_file('sha256', $tmpPath);

            // 最終ファイル名にハッシュを含める
            $fileName = "master_{$hash}.sqlite";
            $finalPath = $this->outputDir . '/' . $fileName;

            // 同じハッシュのファイルが既存ならそのまま使う
            if (!file_exists($finalPath)) {
                rename($tmpPath, $finalPath);
            } else {
                unlink($tmpPath);
            }

            return [
                'file_path'   => $finalPath,
                'file_name'   => $fileName,
                'hash'        => $hash,
                'file_size'   => filesize($finalPath),
                'table_count' => count($tableNames),
                'public_url'  => '/masterdata/' . $fileName,
            ];

        } catch (\Throwable $e) {
            // 一時ファイルをクリーンアップ
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
            throw new RuntimeException('SQLiteエクスポートに失敗しました: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * MySQLのカラム情報からSQLiteのCREATE TABLE文を生成する
     *
     * 複合主キー（l10nテーブル等）に対応:
     * - PRI が複数ある場合は個別のカラム定義に PRIMARY KEY を付けず
     *   末尾に PRIMARY KEY (col1, col2) を追加する
     * - PRI が1つ + AUTO_INCREMENT → INTEGER PRIMARY KEY AUTOINCREMENT
     */
    private function buildCreateTableSql(string $tableName, array $columns): string
    {
        $pkColumns     = [];
        $autoIncrement = null;

        // まず主キー構成を確認
        foreach ($columns as $col) {
            $col = (array) $col;
            if ($col['Key'] === 'PRI') {
                $pkColumns[] = $col['Field'];
                if (str_contains($col['Extra'] ?? '', 'auto_increment')) {
                    $autoIncrement = $col['Field'];
                }
            }
        }

        $isCompositePk = count($pkColumns) > 1;
        $colDefs       = [];

        foreach ($columns as $col) {
            $col  = (array) $col;
            $name = $col['Field'];
            $type = strtolower($col['Type']);
            $null = $col['Null'] === 'YES' ? '' : ' NOT NULL';

            // 型変換
            if (preg_match('/^(int|bigint|tinyint|smallint|mediumint)/', $type)) {
                $sqliteType = 'INTEGER';
            } elseif (preg_match('/^(decimal|float|double|numeric)/', $type)) {
                $sqliteType = 'REAL';
            } else {
                $sqliteType = 'TEXT';
            }

            if (!$isCompositePk && $col['Key'] === 'PRI' && $autoIncrement === $name) {
                // 単一PK + AUTO_INCREMENT
                $colDefs[] = "`{$name}` INTEGER PRIMARY KEY AUTOINCREMENT";
            } elseif (!$isCompositePk && $col['Key'] === 'PRI') {
                // 単一PK（AUTO_INCREMENTなし）
                $colDefs[] = "`{$name}` {$sqliteType}{$null} PRIMARY KEY";
            } else {
                // 複合PKの場合は個別に PRIMARY KEY を付けない
                $colDefs[] = "`{$name}` {$sqliteType}{$null}";
            }
        }

        // 複合主キーは末尾に追加
        if ($isCompositePk) {
            $pkCols    = implode(', ', array_map(fn($c) => "`{$c}`", $pkColumns));
            $colDefs[] = "PRIMARY KEY ({$pkCols})";
        }

        return "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n  "
            . implode(",\n  ", $colDefs)
            . "\n)";
    }
}
