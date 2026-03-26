<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait UseCaseTrait
{
    const LOG_INSERT_OUTSIDE_TRANSACTION = true; // ログのクエリをトランザクション外で実行するオプション

    /**
     * トランザクション付きでコールバックを実行
     *
     * 処理フロー：
     * 1. クリーンアップ処理（オプション、sign_in時のみ）
     * 2. コールバックを実行（クエリはQueryManagerにキューイング）
     * 3. トランザクション開始（trx, log接続）
     * 4. キューに溜まったクエリを実行
     * 5. コミットまたはロールバック
     *
     * @param callable $callback 実行するビジネスロジック
     * @param int|null $sysPlayerId sign_in時のクリーンアップ用プレイヤーID
     * @return mixed コールバックの戻り値
     * @throws \Exception|\Throwable トランザクション実行中にエラーが発生した場合
     */
    public function executeWithTransaction(callable $callback, ?int $sysPlayerId = null)
    {
        // sign_in時のクリーンアップ処理（is_delete=trueのレコードを削除キューに追加）
        if ($sysPlayerId !== null) {
            $cleanupService = app()->make('App\Domain\Player\Services\CleanupService');
            $cleanupService->cleanupDeletedRecords($sysPlayerId);
        }

        try {
            // コールバックを実行（クエリはQueryManagerにキューイングされる）
            $result = $callback();
        } catch (\Throwable $e) {
            // コールバック実行中に例外が発生した場合は、クエリはまだ実行されていないので、そのまま例外を投げる
            throw $e;
        }

        foreach (['trx', 'log'] as $connection) {
            DB::connection($connection)->beginTransaction();
        }

        $queryTrxManager = app()->make('App\Utilities\QueryTrxManager');
        $queryLogManager = app()->make('App\Utilities\QueryLogManager');
        try {

            /**
             * トランザクション内で実行するクエリは、トランザクション内で実行するオプションがONの場合、トランザクション内で実行する
             * trxクエリは常にトランザクション内で実行する
             * 課金に関するlogクエリもトランザクション内で実行する
             */
            $queryTrxManager->execAllQuery();
            $queryLogManager->execPurchaseQuery();

            /**
             * Log系をコミット後にトランザクション外でINSERTするオプションがOFFの場合、ログのクエリはトランザクション内で実行する
             */
            if (!self::LOG_INSERT_OUTSIDE_TRANSACTION) {
                $queryLogManager->execAllQuery();
            }

            foreach (['trx', 'log'] as $connection ){
                DB::connection($connection)->commit();
            }

        } catch (\Exception $e) {
            foreach (['trx', 'log'] as $connection) {
                DB::connection($connection)->rollBack();
            }

            throw $e;
        }
        // トランザクション外でINSERTするオプションがONの場合、ログのクエリはトランザクション外で実行する
        if (self::LOG_INSERT_OUTSIDE_TRANSACTION) {
            $queryLogManager->execAllQuery();
        }

        return $result;
    }
}
