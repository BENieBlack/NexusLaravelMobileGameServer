<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * mstデータを親テーブル単位のSQLiteファイルへ出力する。
 * __l10nテーブルは親テーブルと同じファイルに含める。
 */
class MasterDataExporter
{
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = public_path('masterdata');
    }

    /**
     * @return array{
     *   hash: string,
     *   file_size: int,
     *   table_count: int,
     *   file_count: int,
     *   tables: array<string, array{hash: string, file_name: string, file_size: int, public_url: string}>
     * }
     */
    public function export(): array
    {
        if (! is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        // 全ファイルを一時ディレクトリへ出力し、マニフェストハッシュ確定後に公開する。
        $stagingDir = $this->outputDir . '/.tmp_' . uniqid('', true);
        mkdir($stagingDir, 0755, true);

        $tableNames = array_map(
            static fn ($row) => array_values((array) $row)[0],
            DB::connection('mst')->select("SHOW TABLES LIKE 'mst_%'")
        );
        $tableGroupArray = $this->groupTables($tableNames);
        $tableResultArray = [];

        try {
            foreach ($tableGroupArray as $groupName => $groupTableArray) {
                $tableResultArray[$groupName] = $this->exportGroup($groupName, $groupTableArray, $stagingDir);
            }

            $manifestHash = hash('sha256', json_encode(
                array_map(static fn ($result) => $result['hash'], $tableResultArray),
                JSON_THROW_ON_ERROR
            ));
            $deploymentDir = $this->outputDir . '/' . $manifestHash;

            if (is_dir($deploymentDir)) {
                File::deleteDirectory($stagingDir);
            } else {
                if (! rename($stagingDir, $deploymentDir)) {
                    throw new RuntimeException('SQLite公開ディレクトリの作成に失敗しました');
                }
            }

            foreach ($tableResultArray as &$tableResult) {
                $tableResult['public_url'] = '/masterdata/' . $manifestHash . '/' . $tableResult['file_name'];
            }
            unset($tableResult);
        } catch (\Throwable $exception) {
            if (is_dir($stagingDir)) {
                File::deleteDirectory($stagingDir);
            }
            throw $exception;
        }

        return [
            'hash' => $manifestHash,
            'file_size' => array_sum(array_column($tableResultArray, 'file_size')),
            'table_count' => count($tableNames),
            'file_count' => count($tableResultArray),
            'tables' => $tableResultArray,
        ];
    }

    /** @param array<int, string> $tableNameArray
     *  @return array<string, array<int, string>>
     */
    private function groupTables(array $tableNameArray): array
    {
        $groupTableArray = [];
        foreach ($tableNameArray as $tableName) {
            $groupName = str_ends_with($tableName, '__l10n')
                ? substr($tableName, 0, -6)
                : $tableName;
            $groupTableArray[$groupName][] = $tableName;
        }

        return $groupTableArray;
    }

    /** @param array<int, string> $tableNameArray
     *  @return array{hash: string, file_name: string, file_size: int, public_url: string}
     */
    private function exportGroup(string $groupName, array $tableNameArray, string $outputDir): array
    {
        $tmpPath = $outputDir . '/' . $groupName . '.sqlite';

        try {
            $sqlite = new \PDO('sqlite:' . $tmpPath);
            $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $sqlite->beginTransaction();

            foreach ($tableNameArray as $tableName) {
                $columns = DB::connection('mst')->select("SHOW COLUMNS FROM `{$tableName}`");
                $sqlite->exec($this->buildCreateTableSql($tableName, $columns));
                $rowCollection = DB::connection('mst')->table($tableName)->get();

                if ($rowCollection->isEmpty()) {
                    continue;
                }

                $columnArray = array_keys((array) $rowCollection->first());
                $columnSql = implode(', ', array_map(static fn ($column) => "`{$column}`", $columnArray));
                $placeholderSql = implode(', ', array_fill(0, count($columnArray), '?'));
                $statement = $sqlite->prepare("INSERT INTO `{$tableName}` ({$columnSql}) VALUES ({$placeholderSql})");

                foreach ($rowCollection as $row) {
                    $statement->execute(array_values((array) $row));
                }
            }

            $sqlite->commit();
            unset($sqlite);

            $hash = hash_file('sha256', $tmpPath);
            $fileName = "{$groupName}_{$hash}.sqlite";
            $finalPath = $outputDir . '/' . $fileName;
            rename($tmpPath, $finalPath);

            return [
                'hash' => $hash,
                'file_name' => $fileName,
                'file_size' => filesize($finalPath),
                'public_url' => '',
            ];
        } catch (\Throwable $exception) {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
            throw new RuntimeException('SQLiteエクスポートに失敗しました: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<int, object> $columns */
    private function buildCreateTableSql(string $tableName, array $columns): string
    {
        $primaryKeyArray = [];
        $autoIncrementColumn = null;
        foreach ($columns as $column) {
            $column = (array) $column;
            if ($column['Key'] === 'PRI') {
                $primaryKeyArray[] = $column['Field'];
                if (str_contains($column['Extra'] ?? '', 'auto_increment')) {
                    $autoIncrementColumn = $column['Field'];
                }
            }
        }

        $compositePrimaryKey = count($primaryKeyArray) > 1;
        $columnDefinitionArray = [];
        foreach ($columns as $column) {
            $column = (array) $column;
            $name = $column['Field'];
            $type = strtolower($column['Type']);
            $null = $column['Null'] === 'YES' ? '' : ' NOT NULL';
            $sqliteType = preg_match('/^(int|bigint|tinyint|smallint|mediumint)/', $type)
                ? 'INTEGER'
                : (preg_match('/^(decimal|float|double|numeric)/', $type) ? 'REAL' : 'TEXT');

            if (! $compositePrimaryKey && $column['Key'] === 'PRI' && $autoIncrementColumn === $name) {
                $columnDefinitionArray[] = "`{$name}` INTEGER PRIMARY KEY AUTOINCREMENT";
            } elseif (! $compositePrimaryKey && $column['Key'] === 'PRI') {
                $columnDefinitionArray[] = "`{$name}` {$sqliteType}{$null} PRIMARY KEY";
            } else {
                $columnDefinitionArray[] = "`{$name}` {$sqliteType}{$null}";
            }
        }

        if ($compositePrimaryKey) {
            $primaryKeySql = implode(', ', array_map(static fn ($column) => "`{$column}`", $primaryKeyArray));
            $columnDefinitionArray[] = "PRIMARY KEY ({$primaryKeySql})";
        }

        return "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n  "
            . implode(",\n  ", $columnDefinitionArray)
            . "\n)";
    }
}
