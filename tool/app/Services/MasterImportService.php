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
     * @param  string  $tableName  インポート先テーブル名（例: mst_item）
     * @param  array<int, string>  $headers  ヘッダ（カラム名）配列
     * @param  array<int, array<string, mixed>>  $rows  データ行配列（連想配列）
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
        $now = now()->format('Y-m-d H:i:s');

        // テーブルに存在するヘッダのみ絞り込む（自動設定カラムは除外）
        $validHeaders = array_filter(
            $headers,
            fn (string $h) => in_array($h, $existingColumns, true)
                && ! in_array($h, self::AUTO_SET_COLUMNS, true),
        );

        if (empty($validHeaders)) {
            throw new RuntimeException(
                "テーブル {$tableName} に対応するカラムがスプレッドシートに見つかりません。"
            );
        }

        $insertData = [];
        $skipped = 0;
        $errors = [];

        // NULLableなカラムを取得（空文字をNULLに変換する対象）
        $nullableColumns = $this->getNullableColumns($tableName);

        foreach ($rows as $index => $row) {
            try {
                $record = [];
                foreach ($validHeaders as $header) {
                    $value = $row[$header] ?? '';
                    // NULLableカラムのみ空文字をNULLに変換。NOT NULLカラムは空文字のまま
                    $converted = ($value === '' && in_array($header, $nullableColumns, true))
                        ? null
                        : $value;

                    // rarity 系カラムは文字列（UR/SSR等）を数値に変換
                    if (in_array($header, self::RARITY_COLUMNS, true) && $converted !== null) {
                        $converted = self::RARITY_MAP[$converted] ?? $converted;
                    }

                    $record[$header] = $converted;
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
                $errors[] = '行 '.($index + 2).': '.$e->getMessage();
                $skipped++;
            }
        }

        if (empty($insertData)) {
            return [
                'table' => $tableName,
                'inserted' => 0,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        }

        // 外部キー制約を無効化したまま TRUNCATE → INSERT を実行する
        // MySQLでは FOREIGN_KEY_CHECKS はセッションスコープのため
        // 同一接続内で有効。TRUNCATE は DDL なのでトランザクション外で実行する。
        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::connection('mst')->table($tableName)->truncate();

            // INSERT はトランザクション内でチャンク単位に実行（大量データ対応）
            DB::connection('mst')->transaction(function () use ($tableName, $insertData) {
                foreach (array_chunk($insertData, 500) as $chunk) {
                    DB::connection('mst')->table($tableName)->insert($chunk);
                }
            });
        } finally {
            // 例外が起きても必ず外部キーチェックを元に戻す
            DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Log::info("マスターインポート完了: {$tableName}", [
            'inserted' => count($insertData),
            'skipped' => $skipped,
        ]);

        return [
            'table' => $tableName,
            'inserted' => count($insertData),
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * レアリティ文字列 → 数値マッピング
     * mst_gacha_rarity_rate / mst_gacha_prize / mst_gacha_step_bonus 等で使用
     */
    private const RARITY_MAP = [
        'UR' => 6,
        'SSR' => 5,
        'SR' => 4,
        'R' => 3,
        'UC' => 2,
        'C' => 1,
    ];

    /**
     * rarity 系のカラム名（tinyint として数値変換が必要）
     */
    private const RARITY_COLUMNS = ['rarity', 'bonus_rarity'];

    /**
     * テーブル名のバリデーション（mst_プレフィックスのみ許可）
     */
    private function validateTableName(string $tableName): void
    {
        if (! str_starts_with($tableName, self::ALLOWED_TABLE_PREFIX)) {
            throw new RuntimeException(
                "インポート対象外のテーブルです: {$tableName}。"
                .self::ALLOWED_TABLE_PREFIX.' で始まるテーブルのみ許可されています。'
            );
        }

        // SQLインジェクション対策: テーブル名に英数字とアンダースコア以外を含まないことを確認
        if (! preg_match('/^[a-z0-9_]+$/', $tableName)) {
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
            // getColumnListing() はキャッシュ問題があるため SHOW COLUMNS を直接使用
            $rows = DB::connection('mst')->select("SHOW COLUMNS FROM `{$tableName}`");
            $columns = array_map(fn ($r) => $r->Field, $rows);
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

    /**
     * NULLableなカラム名一覧を取得する
     *
     * SHOW COLUMNS の Null カラムが 'YES' のもの。
     * 空文字をNULLに変換するか否かの判定に使用する。
     *
     * @return array<int, string>
     */
    private function getNullableColumns(string $tableName): array
    {
        try {
            $rows = DB::connection('mst')->select("SHOW COLUMNS FROM `{$tableName}`");

            return array_map(
                fn ($r) => $r->Field,
                array_filter($rows, fn ($r) => $r->Null === 'YES')
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
