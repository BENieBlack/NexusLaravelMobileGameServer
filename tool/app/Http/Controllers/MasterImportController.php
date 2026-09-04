<?php

namespace App\Http\Controllers;

use App\Services\GoogleSpreadsheetService;
use App\Services\MasterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * MasterImportController
 *
 * Google スプレッドシートからマスターデータをインポートする。
 *
 * エンドポイント:
 * GET  /master-import                    インポート画面
 * GET  /master-import/spreadsheets       スプレッドシート一覧取得 (JSON)
 * GET  /master-import/sheets             シート一覧取得 (JSON)
 * GET  /master-import/preview            プレビューデータ取得 (JSON)
 * POST /master-import/execute            インポート実行 (JSON)
 */
class MasterImportController extends Controller
{
    public function __construct(
        private readonly GoogleSpreadsheetService $spreadsheetService,
        private readonly MasterImportService $importService,
    ) {}

    /**
     * インポート画面を表示する
     */
    public function index(Request $request): Response
    {
        return Inertia::render('MasterImport/Index', [
            'auth' => ['user' => $request->user()],
            'is_configured' => $this->spreadsheetService->isConfigured(),
        ]);
    }

    /**
     * フォルダ内のスプレッドシート一覧を取得する
     */
    public function spreadsheets(): JsonResponse
    {
        try {
            $spreadsheets = $this->spreadsheetService->listSpreadsheets();

            return response()->json([
                'status' => 'success',
                'spreadsheets' => $spreadsheets,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * スプレッドシートのシート一覧を取得する
     */
    public function sheets(Request $request): JsonResponse
    {
        $request->validate([
            'spreadsheet_id' => ['required', 'string'],
        ]);

        try {
            $sheets = $this->spreadsheetService->listSheets(
                $request->input('spreadsheet_id'),
            );

            return response()->json([
                'status' => 'success',
                'sheets' => $sheets,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * インポート前プレビューデータを取得する
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'spreadsheet_id' => ['required', 'string'],
            'sheet_title' => ['required', 'string'],
        ]);

        try {
            $preview = $this->spreadsheetService->getPreviewData(
                $request->input('spreadsheet_id'),
                $request->input('sheet_title'),
            );

            return response()->json([
                'status' => 'success',
                'preview' => $preview, // テーブル名をキーとした連想配列
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * インポートを実行する
     *
     * シート内の全テーブルをインポートする。
     * 1行目のテーブル名ごとに対応するmstテーブルへ書き込む。
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'spreadsheet_id' => ['required', 'string'],
            'sheet_title' => ['required', 'string'],
        ]);

        $spreadsheetId = $request->input('spreadsheet_id');
        $sheetTitle = $request->input('sheet_title');

        try {
            // スプレッドシートからデータ取得（テーブルごとにグループ化済み）
            $tableData = $this->spreadsheetService->getSheetData($spreadsheetId, $sheetTitle);

            if (empty($tableData)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "シート「{$sheetTitle}」にデータが存在しません。",
                    'results' => [],
                ]);
            }

            $results = [];
            foreach ($tableData as $tableName => $data) {
                if (empty($data['rows'])) {
                    $results[] = [
                        'table' => $tableName,
                        'inserted' => 0,
                        'skipped' => 0,
                        'errors' => ['データ行がありません'],
                    ];

                    continue;
                }

                $results[] = $this->importService->import(
                    $tableName,
                    $data['headers'],
                    $data['rows'],
                );
            }

            $totalInserted = array_sum(array_column($results, 'inserted'));
            $tableNames = implode(', ', array_column($results, 'table'));

            return response()->json([
                'status' => 'success',
                'message' => "「{$tableNames}」へ合計 {$totalInserted} 件インポートしました。",
                'results' => $results,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * エラーレスポンスを生成する
     */
    private function errorResponse(Throwable $e): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 422);
    }
}
