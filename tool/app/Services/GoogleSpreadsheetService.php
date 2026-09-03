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
        $this->driveService  = new Drive($this->client);
        $this->sheetsService = new Sheets($this->client);
    }

    /**
     * 設定が有効か確認する
     */
    public function isConfigured(): bool
    {
        $folderId  = config('services.google_spreadsheet.folder_id');
        $credPath  = config('services.google_spreadsheet.credentials_path');

        return !empty($folderId) && !empty($credPath) && file_exists($credPath);
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
            'q'       => "'{$folderId}' in parents and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
            'fields'  => 'files(id, name, modifiedTime)',
            'orderBy' => 'name',
        ]);

        return array_map(fn (DriveFile $file) => [
            'id'          => $file->getId(),
            'name'        => $file->getName(),
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
            'title'    => $sheet->getProperties()->getTitle(),
        ], $spreadsheet->getSheets());
    }

    /**
     * シートの全データを取得する
     *
     * 1行目をヘッダとして使用し、連想配列の配列として返す。
     * ヘッダが空の列は無視する。
     *
     * @return array{
     *   headers: array<int, string>,
     *   rows: array<int, array<string, string>>,
     *   raw_count: int
     * }
     */
    public function getSheetData(string $spreadsheetId, string $sheetTitle): array
    {
        /** @var ValueRange $response */
        $response = $this->sheetsService->spreadsheets_values->get(
            $spreadsheetId,
            $sheetTitle,
        );

        $values = $response->getValues() ?? [];

        if (empty($values)) {
            return ['headers' => [], 'rows' => [], 'raw_count' => 0];
        }

        // 1行目をヘッダとして使用
        $headers = array_filter(
            array_map('strval', $values[0]),
            fn (string $h) => $h !== '',
        );
        $headerCount = count($headers);
        $headerKeys  = array_values($headers);

        $rows = [];
        foreach (array_slice($values, 1) as $row) {
            // 全列が空の行はスキップ
            if (empty(array_filter($row, fn ($v) => $v !== '' && $v !== null))) {
                continue;
            }

            $assoc = [];
            for ($i = 0; $i < $headerCount; $i++) {
                $assoc[$headerKeys[$i]] = isset($row[$i]) ? strval($row[$i]) : '';
            }
            $rows[] = $assoc;
        }

        return [
            'headers'   => $headerKeys,
            'rows'      => $rows,
            'raw_count' => count($values) - 1, // ヘッダ行を除いた元の行数
        ];
    }

    /**
     * プレビュー用データを取得する（先頭N行）
     *
     * @return array{
     *   headers: array<int, string>,
     *   rows: array<int, array<string, string>>,
     *   total_rows: int,
     *   preview_rows: int
     * }
     */
    public function getPreviewData(string $spreadsheetId, string $sheetTitle, int $limit = 5): array
    {
        $data = $this->getSheetData($spreadsheetId, $sheetTitle);

        return [
            'headers'      => $data['headers'],
            'rows'         => array_slice($data['rows'], 0, $limit),
            'total_rows'   => count($data['rows']),
            'preview_rows' => min($limit, count($data['rows'])),
        ];
    }

    /**
     * Google API クライアントを構築する
     */
    private function buildClient(): Client
    {
        $credentialsPath = config('services.google_spreadsheet.credentials_path');

        if (empty($credentialsPath)) {
            throw new RuntimeException(
                'TOL_GOOGLE_SERVICE_ACCOUNT_JSON が設定されていません。'
            );
        }

        if (!file_exists($credentialsPath)) {
            throw new RuntimeException(
                "サービスアカウント認証ファイルが見つかりません: {$credentialsPath}"
            );
        }

        $client = new Client();
        $client->setApplicationName('Nexus Master Importer');
        $client->setScopes([
            Drive::DRIVE_READONLY,
            Sheets::SPREADSHEETS_READONLY,
        ]);
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
