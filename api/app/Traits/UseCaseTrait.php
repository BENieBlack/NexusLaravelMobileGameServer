<?php

namespace App\Traits;

use NexusUnitOfWork\Contracts\QueryManagerInterface;
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

        $queryManager = app()->make(QueryManagerInterface::class);
        
        try {
            // コールバックを実行（クエリはQueryManagerにキューイングされる）
            // PlayerServiceなどでexecAllQuery()が呼ばれた場合、トランザクション内で実行される
            $result = $callback();

            /**
             * すべてのクエリをフラッシュ（購入ログ、Sys/Trx/通常Logを実行）
             */
            $queryManager->flush();

            /**
             * Log系をコミット後にトランザクション外でINSERTするオプションがOFFの場合は既に実行済み
             */

            foreach (['sys', 'trx', 'log'] as $connection ){
                DB::connection($connection)->commit();
            }

        } catch (Exception | Throwable $e) {
            foreach (['sys', 'trx', 'log'] as $connection) {
                DB::connection($connection)->rollBack();
            }

            throw $e;
        }
        
        // トランザクション外でINSERTするオプションは削除（新QueryManagerでは常にトランザクション内実行）

        return $result;
    }
}
