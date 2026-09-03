<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MasterImportService
 *
 * スプレッドシートから取得したデータをmstデータベースに
 * インポートするサービス。
 *
 * インポート仕様:
 * - シート名 = mstテーブル名 として対応付ける
 *   例: シート名「mst_item」→ テーブル「mst_item」
 * - 1行目はヘッダ（カラム名）として使用
 * - 既存レコードは全て削除してから再INSERT（truncate & insert）
 * - deploy_key は現在のUnixタイムスタンプを使用
 * - トランザクション内で実行（失敗時はロールバック）
 */
class MasterImportService
{
    /**
     * インポート対象として許可するテーブル名のプレフィックス
     */
    private const ALLOWED_TABLE_PREFIX = 'mst_';

    /**
     * インポート時に自動設定するカラム
     * スプレッドシートに含まれていても無視し、サーバー側で上書きする
     */
    private const AUTO_SET_COLUMNS = ['created_at', 'updated_at'];

    /**
     * シートデータをmstテーブルへインポートする
     *
     * @param  string  $tableName   インポート先テーブル名（例: mst_item）
     * @param  array   $headers     ヘッダ（カラム名）配列
     * @param  array   $rows        データ行配列（連想配列）
     * @return array{
     *   table: string,
     *   inserted: int,
     *   skipped: int,
     *   errors: array<int, string>
     * }
     */
    public function import(string $tableName, array $headers, array $rows): array
    {
        $this->validateTableName($tableName);

        $existingColumns = $this->getTableColumns($tableName);
        $now             = now()->format('Y-m-d H:i:s');

        // テーブルに存在するヘッダのみ絞り込む（自動設定カラムは除外）
        $validHeaders = array_filter(
            $headers,
            fn (string $h) => in_array($h, $existingColumns, true)
                && !in_array($h, self::AUTO_SET_COLUMNS, true),
        );

        if (empty($validHeaders)) {
            throw new RuntimeException(
                "テーブル {$tableName} に対応するカラムがスプレッドシートに見つかりません。"
            );
        }

        $insertData = [];
        $skipped    = 0;
        $errors     = [];

        foreach ($rows as $index => $row) {
            try {
                $record = [];
                foreach ($validHeaders as $header) {
                    $value = $row[$header] ?? '';
                    // 空文字はNULLに変換（NULLableカラム対応）
                    $record[$header] = $value === '' ? null : $value;
                }

                // created_at / updated_at を自動設定
                if (in_array('created_at', $existingColumns, true)) {
                    $record['created_at'] = $now;
                }
                if (in_array('updated_at', $existingColumns, true)) {
                    $record['updated_at'] = $now;
                }

                $insertData[] = $record;
            } catch (\Throwable $e) {
                $errors[] = "行 " . ($index + 2) . ": " . $e->getMessage();
                $skipped++;
            }
        }

        if (empty($insertData)) {
            return [
                'table'    => $tableName,
                'inserted' => 0,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ];
        }

        DB::connection('mst')->transaction(function () use ($tableName, $insertData) {
            // 既存データを全削除
            DB::connection('mst')->table($tableName)->truncate();

            // チャンク単位でINSERT（大量データ対応）
            foreach (array_chunk($insertData, 500) as $chunk) {
                DB::connection('mst')->table($tableName)->insert($chunk);
            }
        });

        Log::info("マスターインポート完了: {$tableName}", [
            'inserted' => count($insertData),
            'skipped'  => $skipped,
        ]);

        return [
            'table'    => $tableName,
            'inserted' => count($insertData),
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    /**
     * テーブル名のバリデーション（mst_プレフィックスのみ許可）
     */
    private function validateTableName(string $tableName): void
    {
        if (!str_starts_with($tableName, self::ALLOWED_TABLE_PREFIX)) {
            throw new RuntimeException(
                "インポート対象外のテーブルです: {$tableName}。"
                . self::ALLOWED_TABLE_PREFIX . " で始まるテーブルのみ許可されています。"
            );
        }

        // SQLインジェクション対策: テーブル名に英数字とアンダースコア以外を含まないことを確認
        if (!preg_match('/^[a-z0-9_]+$/', $tableName)) {
            throw new RuntimeException(
                "テーブル名に不正な文字が含まれています: {$tableName}"
            );
        }
    }

    /**
     * テーブルの実際のカラム名一覧を取得する
     *
     * @return array<int, string>
     */
    private function getTableColumns(string $tableName): array
    {
        try {
            $columns = DB::connection('mst')->getSchemaBuilder()->getColumnListing($tableName);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "テーブル {$tableName} が mst データベースに存在しません。"
            );
        }

        if (empty($columns)) {
            throw new RuntimeException(
                "テーブル {$tableName} のカラム情報を取得できませんでした。"
            );
        }

        return $columns;
    }
}
