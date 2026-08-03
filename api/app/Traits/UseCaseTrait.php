<?php

namespace App\Traits;

use NexusUnitOfWork\Contracts\QueryManagerInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

trait UseCaseTrait
{
    /**
     * トランザクション付きでコールバックを実行
     *
     * 処理フロー：
     * 1. クリーンアップ処理（オプション、sign_in時のみ）
     * 2. トランザクション開始（sys, trx 接続のみ）
     * 3. コールバックを実行（クエリはQueryManagerにキューイング、sysは即座に実行してIDを取得）
     * 4. キューに溜まったクエリを実行（ログ以外）
     * 5. コミットまたはロールバック（sys, trx のみ）
     * 6. ログをトランザクション外で実行（コミット後）
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
        // ログはトランザクションから除外（非原子的な複数DBトランザクション問題を解消）
        foreach (['sys', 'trx'] as $connection) {
            DB::connection($connection)->beginTransaction();
        }

        $queryManager = app()->make(QueryManagerInterface::class);
        
        try {
            // コールバックを実行（クエリはQueryManagerにキューイングされる）
            // PlayerServiceなどでexecAllQuery()が呼ばれた場合、トランザクション内で実行される
            $result = $callback();

            /**
             * すべてのクエリをフラッシュ（ログ以外のSys/Trxを実行）
             * ログはトランザクション外で実行するため、ここでは実行されない
             */
            $queryManager->flush();

            // sys, trx のみコミット
            foreach (['sys', 'trx'] as $connection ){
                DB::connection($connection)->commit();
            }

            // **ログをトランザクション外で実行**（P1-1: ログトランザクション分離）
            // ログ書き込み失敗はビジネストランザクションに影響しない
            $queryManager->execAllLogs();

        } catch (Exception | Throwable $e) {
            \Log::error('Transaction failed in UseCase', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // sys, trx のみロールバック
            foreach (['sys', 'trx'] as $connection) {
                DB::connection($connection)->rollBack();
            }

            throw $e;
        }

        return $result;
    }
}
