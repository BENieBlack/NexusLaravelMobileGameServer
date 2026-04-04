<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

trait UseCaseTrait
{
    const LOG_INSERT_OUTSIDE_TRANSACTION = true; // ログのクエリをトランザクション外で実行するオプション

    /**
     * トランザクション付きでコールバックを実行
     *
     * 処理フロー：
     * 1. クリーンアップ処理（オプション、sign_in時のみ）
     * 2. トランザクション開始（sys, trx, log接続）
     * 3. コールバックを実行（クエリはQueryManagerにキューイング、sysは即座に実行してIDを取得）
     * 4. キューに溜まったクエリを実行
     * 5. コミットまたはロールバック
     *
     * @param callable $callback 実行するビジネスロジック
     * @param int|null $sysPlayerId sign_in時のクリーンアップ用プレイヤーID
     * @return mixed コールバックの戻り値
     * @throws Exception|Throwable トランザクション実行中にエラーが発生した場合
     */
    public function executeWithTransaction(callable $callback, ?int $sysPlayerId = null): mixed
    {
        // sign_in時のクリーンアップ処理（is_delete=trueのレコードを削除キューに追加）
        if ($sysPlayerId !== null) {
            $cleanupService = app()->make('App\Domain\Player\Services\CleanupService');
            $cleanupService->cleanupDeletedRecords($sysPlayerId);
        }

        // **重要**: トランザクションを先に開始してから$callback()を実行
        // これにより、PlayerServiceでexecAllQuery()を呼び出したときにトランザクション内で実行される
        foreach (['sys', 'trx', 'log'] as $connection) {
            DB::connection($connection)->beginTransaction();
        }

        $queryManager = app()->make(\App\Persistence\QueryManager::class);
        
        try {
            // コールバックを実行（クエリはQueryManagerにキューイングされる）
            // PlayerServiceなどでexecAllQuery()が呼ばれた場合、トランザクション内で実行される
            $result = $callback();

            /**
             * トランザクション内で実行するクエリは、トランザクション内で実行するオプションがONの場合、トランザクション内で実行する
             * sysクエリは常にトランザクション内で実行する（IDを取得して外部キーとして使用）
             * trxクエリは常にトランザクション内で実行する
             * 課金に関するlogクエリもトランザクション内で実行する
             */
            $queryManager->execPurchaseQuery(); // 課金ログを先に実行
            $queryManager->execAllQuery(); // Sys/Trx/通常Logを実行

            /**
             * Log系をコミット後にトランザクション外でINSERTするオプションがOFFの場合、ログのクエリはトランザクション内で実行する
             */
            if (!self::LOG_INSERT_OUTSIDE_TRANSACTION) {
                $queryManager->execAllQuery();
            }

            foreach (['sys', 'trx', 'log'] as $connection ){
                DB::connection($connection)->commit();
            }

        } catch (Exception | Throwable $e) {
            foreach (['sys', 'trx', 'log'] as $connection) {
                DB::connection($connection)->rollBack();
            }

            throw $e;
        }
        // トランザクション外でINSERTするオプションがONの場合、ログのクエリはトランザクション外で実行する
        if (self::LOG_INSERT_OUTSIDE_TRANSACTION) {
            $queryManager->execAllQuery();
        }

        return $result;
    }
}
