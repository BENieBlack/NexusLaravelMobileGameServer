<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use RuntimeException;

/**
 * GoogleSpreadsheetService
 *
 * Google Drive フォルダ内のスプレッドシートを操作するサービス。
 *
 * 設定:
 * - TOL_GOOGLE_SPREADSHEET_DIR: Google Drive のフォルダID
 * - TOL_GOOGLE_SERVICE_ACCOUNT_JSON: サービスアカウント認証JSONファイルのパス
 *
 * 認証方法:
 * Google Cloud Console でサービスアカウントを作成し、
 * Drive API / Sheets API を有効化した上で認証JSONを取得してください。
 * 対象フォルダをサービスアカウントのメールアドレスに共有する必要があります。
 */
class GoogleSpreadsheetService
{
    private Client $client;

    private Drive $driveService;

    private Sheets $sheetsService;

    public function __construct()
    {
        $this->client = $this->buildClient();
        $this->driveService = new Drive($this->client);
        $this->sheetsService = new Sheets($this->client);
    }

    /**
     * 設定が有効か確認する
     */
    public function isConfigured(): bool
    {
        $folderId = config('services.google_spreadsheet.folder_id');
        if (empty($folderId)) {
            return false;
        }

        // JSON直書き または ファイルパスのどちらかが設定されていればOK
        $jsonContent = config('services.google_spreadsheet.credentials_json');
        if (! empty($jsonContent)) {
            return true;
        }

        $credPath = config('services.google_spreadsheet.credentials_path');

        return ! empty($credPath) && file_exists($credPath);
    }

    /**
     * フォルダ内のスプレッドシート一覧を取得する
     *
     * @return array<int, array{id: string, name: string, modified_at: string}>
     */
    public function listSpreadsheets(): array
    {
        $folderId = $this->getFolderId();

        $response = $this->driveService->files->listFiles([
            'q' => "'{$folderId}' in parents and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
            'fields' => 'files(id, name, modifiedTime)',
            'orderBy' => 'name',
        ]);

        return array_map(fn (DriveFile $file) => [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'modified_at' => $file->getModifiedTime(),
        ], $response->getFiles());
    }

    /**
     * スプレッドシートのシート一覧を取得する
     *
     * @return array<int, array{sheet_id: int, title: string}>
     */
    public function listSheets(string $spreadsheetId): array
    {
        $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheetId);

        return array_map(fn ($sheet) => [
            'sheet_id' => $sheet->getProperties()->getSheetId(),
            'title' => $sheet->getProperties()->getTitle(),
        ], $spreadsheet->getSheets());
    }

    /**
     * is_active の列インデックス（固定: 常に1列目 = インデックス0）
     *
     * スプレッドシートの1列目は is_active 列として予約する。
     * 1行目（テーブル名行）は空にし、2行目（カラム名行）に "is_active" と記載する。
     * 空・"0"・"false" の場合はその行を取り込み対象外とする。
     */
    private const IS_ACTIVE_COLUMN_INDEX = 0;

    /**
     * is_active が有効とみなす値
     */
    private const ACTIVE_VALUES = ['1', 'true', 'TRUE', 'True'];

    /**
     * シートの全データを取得する
     *
     * スプレッドシートのヘッダ形式（2行ヘッダ）:
     *   1列目: is_active（1行目は空、2行目は "is_active"）← 固定
     *   1行目: テーブル名（空セルは直前のテーブル名を引き継ぐ）
     *   2行目: カラム名
     *   3行目以降: データ
     *
     * is_active が空・0・false の行は全テーブルで取り込み対象外。
     *
     * l10nテーブルの列は「カラム名.言語コード」形式（例: name.ja, name.en）で表現する。
     * これをサービス側で言語ごとの行に展開する。
     * 例: { mst_unit_id: "unit_001", "name.ja": "炎の剣士", "name.en": "Flame Swordsman" }
     *   → mst_unit__l10n の ja 行: { mst_unit_id: "unit_001", language: "ja", name: "炎の剣士" }
     *   → mst_unit__l10n の en 行: { mst_unit_id: "unit_001", language: "en", name: "Flame Swordsman" }
     *
     * @return array<string, array{
     *   table: string,
     *   headers: array<int, string>,
     *   rows: array<int, array<string, string>>,
     *   raw_count: int,
     *   skipped_count: int
     * }>
     */
    public function getSheetData(string $spreadsheetId, string $sheetTitle): array
    {
        /** @var ValueRange $response */
        $response = $this->sheetsService->spreadsheets_values->get(
            $spreadsheetId,
            $sheetTitle,
        );

        // ライブラリの宣言は array だが、空シートでは null が返る
        /** @var array<int, array<int, string>>|null $rawValues */
        $rawValues = $response->getValues();
        $values = $rawValues ?? [];

        if (count($values) < 2) {
            return [];
        }

        $tableRow = $values[0]; // 1行目: テーブル名
        $headerRow = $values[1]; // 2行目: カラム名

        // is_active 列の存在チェック（必須）
        $isActiveHeader = strval($headerRow[self::IS_ACTIVE_COLUMN_INDEX] ?? '');
        if ($isActiveHeader !== 'is_active') {
            throw new RuntimeException(
                "シート「{$sheetTitle}」の1列目は \"is_active\" である必要があります。"
                ."現在の値: \"{$isActiveHeader}\"\n"
                .'スプレッドシートの1列目（A列）に is_active 列を追加してください。'
            );
        }
        $dataRows = array_slice($values, 2);

        // 列ごとに「テーブル名」と「カラム名」を対応付ける
        // 1列目は is_active 予約列なので除外し、インデックス1以降を処理する
        // 1行目が空の場合は直前のテーブル名を引き継ぐ
        $columnMeta = [];
        $lastTable = '';
        for ($i = 0; $i < count($headerRow); $i++) {
            $tableName = strval($tableRow[$i] ?? '');
            $columnName = strval($headerRow[$i] ?? '');

            // 1列目（is_active列）はテーブルデータとして扱わない
            if ($i === self::IS_ACTIVE_COLUMN_INDEX) {
                continue;
            }

            if ($tableName !== '') {
                $lastTable = $tableName;
            }
            if ($columnName === '' || $lastTable === '') {
                continue;
            }
            $columnMeta[] = ['table' => $lastTable, 'column' => $columnName, 'index' => $i];
        }

        // テーブルごとにグループ化
        $tables = [];
        foreach ($columnMeta as $meta) {
            $tables[$meta['table']][] = $meta;
        }

        // 親テーブルの id 列インデックスを事前に取得しておく
        // l10nテーブル行に親IDを自動注入するために使用
        // 例: mst_unit__l10n → mst_unit の id 列インデックス
        $parentIdIndexMap = []; // ['mst_unit__l10n' => 3] のような形
        foreach ($tables as $tableName => $columns) {
            if (! str_ends_with($tableName, '__l10n')) {
                continue;
            }
            $parentTableName = substr($tableName, 0, -strlen('__l10n'));
            if (! isset($tables[$parentTableName])) {
                continue;
            }
            foreach ($tables[$parentTableName] as $col) {
                if ($col['column'] === 'id') {
                    $parentIdIndexMap[$tableName] = $col['index'];
                    break;
                }
            }
        }

        // テーブルごとにデータ行を連想配列化
        $result = [];
        foreach ($tables as $tableName => $columns) {
            $rows = [];
            $skippedCount = 0;

            foreach ($dataRows as $row) {
                // is_active チェック（1列目）: 空・0・false は取り込み対象外
                $isActive = strval($row[self::IS_ACTIVE_COLUMN_INDEX] ?? '');
                if (! in_array($isActive, self::ACTIVE_VALUES, true)) {
                    $skippedCount++;

                    continue;
                }

                $assoc = [];
                foreach ($columns as $col) {
                    $assoc[$col['column']] = isset($row[$col['index']]) ? strval($row[$col['index']]) : '';
                }

                // l10nテーブルの場合、同行の親テーブルの id を _parent_id として注入
                if (str_ends_with($tableName, '__l10n') && isset($parentIdIndexMap[$tableName])) {
                    $assoc['_parent_id'] = strval($row[$parentIdIndexMap[$tableName]] ?? '');
                }

                // 全列が空の行はスキップ（is_active以外）
                if (empty(array_filter($assoc, fn ($v) => $v !== ''))) {
                    continue;
                }
                $rows[] = $assoc;
            }

            // l10nテーブル: 「カラム名.言語コード」列を言語ごとの行に展開する
            // 親テーブル名を導出（例: mst_unit__l10n → mst_unit）
            if (str_ends_with($tableName, '__l10n')) {
                $parentTableName = substr($tableName, 0, -strlen('__l10n'));
                $rows = $this->expandL10nRows($rows, $parentTableName);
                $headers = $this->buildL10nHeaders($columns, $parentTableName);
            } else {
                $headers = array_column($columns, 'column');
            }

            $result[$tableName] = [
                'table' => $tableName,
                'headers' => $headers,
                'rows' => $rows,
                'raw_count' => count($dataRows),
                'skipped_count' => $skippedCount,
            ];
        }

        return $result;
    }

    /**
     * l10n行を言語ごとの行に展開する
     *
     * スプレッドシートでは親IDを持たず、事前に注入された `_parent_id` の値を
     * `{parentTable}_id` として自動セットする。
     *
     * 入力 (mst_unit__l10n): [{ "_parent_id": "unit_001", "name.ja": "炎の剣士", "name.en": "Flame Swordsman" }]
     *
     * 出力:
     *   { mst_unit_id: "unit_001", language: "ja", name: "炎の剣士" }
     *   { mst_unit_id: "unit_001", language: "en", name: "Flame Swordsman" }
     *
     * @param  array<int, array<string, string>>  $rows  l10n列のデータ（_parent_id付き）
     * @param  string  $parentTableName  親テーブル名（例: mst_unit）
     * @return array<int, array<string, string>>
     */
    private function expandL10nRows(array $rows, string $parentTableName): array
    {
        $expanded = [];
        $parentIdKey = $parentTableName.'_id'; // 例: mst_unit_id

        foreach ($rows as $row) {
            // _parent_id を取り出して親ID列として使用
            $parentId = $row['_parent_id'] ?? '';

            // カラム名に「.言語コード」を含むものを抽出して言語コードを収集
            $languages = [];
            $langColumns = []; // ['ja' => ['name' => '炎の剣士', ...], ...]
            $commonFields = []; // 言語に依存しないフィールド（deploy_key 等）

            foreach ($row as $key => $value) {
                if ($key === '_parent_id') {
                    continue; // 内部キーはスキップ
                }
                if (str_contains($key, '.')) {
                    [$colName, $lang] = explode('.', $key, 2);
                    $languages[$lang] = true;
                    $langColumns[$lang][$colName] = $value;
                } else {
                    $commonFields[$key] = $value;
                }
            }

            // 言語ごとに1行生成
            foreach ($languages as $lang => $_) {
                $langRow = [$parentIdKey => $parentId] + $commonFields;
                $langRow['language'] = $lang;
                foreach ($langColumns[$lang] as $col => $val) {
                    $langRow[$col] = $val;
                }
                // 言語列の値が全て空なら行をスキップ
                $langValues = array_diff_key($langRow, [$parentIdKey => ''], $commonFields, ['language' => '']);
                if (empty(array_filter($langValues, fn ($v) => $v !== ''))) {
                    continue;
                }
                $expanded[] = $langRow;
            }
        }

        return $expanded;
    }

    /**
     * l10nテーブル用のヘッダ列を構築する
     *
     * スプレッドシートの l10n 列には親IDは含まれず、
     * `{parentTable}_id`、`language`、言語依存カラムの順でヘッダを返す。
     *
     * @param  array<int, array{table: string, column: string, index: int}>  $columns
     * @param  string  $parentTableName
     * @return array<int, string>
     */
    private function buildL10nHeaders(array $columns, string $parentTableName): array
    {
        $parentIdKey = $parentTableName.'_id'; // 例: mst_unit_id
        $headers = [];
        $langCols = [];

        foreach ($columns as $col) {
            if (str_contains($col['column'], '.')) {
                [$colName] = explode('.', $col['column'], 2);
                $langCols[$colName] = true;
            } elseif ($col['column'] !== 'id') {
                // id 列は親ID列として変換するため除外
                $headers[] = $col['column'];
            }
        }

        // 先頭に {parentTable}_id を追加
        array_unshift($headers, $parentIdKey);

        // language 列を追加
        $headers[] = 'language';

        // 言語依存列を追加
        foreach (array_keys($langCols) as $colName) {
            $headers[] = $colName;
        }

        return $headers;
    }

    /**
     * プレビュー用データを取得する（テーブルごとに先頭N行）
     *
     * @return array<string, array{
     *   table: string,
     *   headers: array<int, string>,
     *   rows: array<int, array<string, string>>,
     *   total_rows: int,
     *   preview_rows: int
     * }>
     */
    public function getPreviewData(string $spreadsheetId, string $sheetTitle, int $limit = 5): array
    {
        $tableData = $this->getSheetData($spreadsheetId, $sheetTitle);

        $result = [];
        foreach ($tableData as $tableName => $data) {
            $result[$tableName] = [
                'table' => $tableName,
                'headers' => $data['headers'],
                'rows' => array_slice($data['rows'], 0, $limit),
                'total_rows' => count($data['rows']),
                'preview_rows' => min($limit, count($data['rows'])),
            ];
        }

        return $result;
    }

    /**
     * Google API クライアントを構築する
     *
     * 認証方法の優先順位:
     * 1. TOL_GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT（JSON直書き）
     * 2. TOL_GOOGLE_SERVICE_ACCOUNT_JSON（ファイルパス）
     */
    private function buildClient(): Client
    {
        $client = new Client;
        $client->setApplicationName('Nexus Master Importer');
        $client->setScopes([
            Drive::DRIVE_READONLY,
            Sheets::SPREADSHEETS,          // 読み書き両方（サンプルデータ書き込み用）
        ]);

        // JSON直書きを優先
        $jsonContent = config('services.google_spreadsheet.credentials_json');
        if (! empty($jsonContent)) {
            // private_key の \n をPHPの改行コードに変換
            $decoded = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'TOL_GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT のJSONが不正です: '.json_last_error_msg()
                );
            }
            // private_key 内の \\n を実際の改行に変換（.envでエスケープされる場合）
            if (isset($decoded['private_key'])) {
                $decoded['private_key'] = str_replace('\\n', "\n", $decoded['private_key']);
            }
            $client->setAuthConfig($decoded);

            return $client;
        }

        // ファイルパスにフォールバック
        $credentialsPath = config('services.google_spreadsheet.credentials_path');
        if (empty($credentialsPath)) {
            throw new RuntimeException(
                'TOL_GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT または TOL_GOOGLE_SERVICE_ACCOUNT_JSON を設定してください。'
            );
        }
        if (! file_exists($credentialsPath)) {
            throw new RuntimeException(
                "サービスアカウント認証ファイルが見つかりません: {$credentialsPath}"
            );
        }
        $client->setAuthConfig($credentialsPath);

        return $client;
    }

    /**
     * 設定からフォルダIDを取得する
     */
    private function getFolderId(): string
    {
        $folderId = config('services.google_spreadsheet.folder_id');

        if (empty($folderId)) {
            throw new RuntimeException(
                'TOL_GOOGLE_SPREADSHEET_DIR が設定されていません。'
            );
        }

        return $folderId;
    }
}
