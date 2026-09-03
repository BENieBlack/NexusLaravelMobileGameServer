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
            'auth'         => ['user' => $request->user()],
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
                'status'       => 'success',
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
            'sheet_title'    => ['required', 'string'],
        ]);

        try {
            $preview = $this->spreadsheetService->getPreviewData(
                $request->input('spreadsheet_id'),
                $request->input('sheet_title'),
            );

            return response()->json([
                'status'  => 'success',
                'preview' => $preview,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * インポートを実行する
     *
     * シート名をテーブル名として使用する。
     * 例: シート名「mst_item」→ mst データベースの mst_item テーブルへインポート
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'spreadsheet_id' => ['required', 'string'],
            'sheet_title'    => ['required', 'string'],
        ]);

        $spreadsheetId = $request->input('spreadsheet_id');
        $sheetTitle    = $request->input('sheet_title');

        // シート名をテーブル名として使用
        $tableName = $sheetTitle;

        try {
            // スプレッドシートからデータ取得
            $data = $this->spreadsheetService->getSheetData($spreadsheetId, $sheetTitle);

            if (empty($data['rows'])) {
                return response()->json([
                    'status'  => 'warning',
                    'message' => "シート「{$sheetTitle}」にデータが存在しません。",
                    'result'  => null,
                ]);
            }

            // mstデータベースへインポート
            $result = $this->importService->import(
                $tableName,
                $data['headers'],
                $data['rows'],
            );

            return response()->json([
                'status'  => 'success',
                'message' => "「{$tableName}」へ {$result['inserted']} 件インポートしました。",
                'result'  => $result,
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
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 422);
    }
}
