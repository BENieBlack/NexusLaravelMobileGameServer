<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\DB;
use NexusPitr\Logger\ShardMapper;
use NexusUnitOfWork\Contracts\QueryManagerInterface;
use Throwable;

trait UseCaseTrait
{
    /**
     * トランザクション付きでコールバックを実行
     *
     * 処理フロー：
     * 1. クリーンアップ処理（オプション、sign_in時のみ）
     * 2. トランザクション開始（sys, trx, log を同時に）
     * 3. コールバックを実行（クエリはQueryManagerにキューイング）
     * 4. キューに溜まったクエリを実行（TrxDB + LogDB）
     * 5. すべてを同時にコミット（1つでも失敗したら全てロールバック）
     * 6. 通常ログをトランザクション外で実行（コミット後）
     *
     * @param  callable  $callback  実行するビジネスロジック
     * @param  int|null  $sysPlayerId  sign_in時のクリーンアップ用プレイヤーID
     * @return mixed コールバックの戻り値
     *
     * @throws Exception|Throwable トランザクション実行中にエラーが発生した場合
     */
    public function executeWithTransaction(callable $callback, ?int $sysPlayerId = null): mixed
    {
        // sign_in時のクリーンアップ処理（is_delete=trueのレコードを削除キューに追加）
        if ($sysPlayerId !== null) {
            $cleanupService = app()->make('App\Domain\Player\Services\PlayerCleanupService');
            $cleanupService->cleanupDeletedRecords($sysPlayerId);
        }

        // 使用する接続を収集（sys + trx + log）
        $connections = $this->resolveActiveConnections();

        // すべてのトランザクションを同時に開始（PITR整合性保証）
        foreach ($connections as $conn) {
            DB::connection($conn)->beginTransaction();
        }

        $queryManager = app()->make(QueryManagerInterface::class);

        try {
            // コールバックを実行（クエリはQueryManagerにキューイングされる）
            $result = $callback();

            /**
             * すべてのクエリをフラッシュ（TrxDB + LogDB PITRログ）
             * PITRログも同一トランザクション内で実行される
             */
            $queryManager->flush();

            // すべてを同時にコミット（完全な整合性保証）
            foreach ($connections as $conn) {
                DB::connection($conn)->commit();
            }

            // **通常ログをトランザクション外で実行**
            // 通常ログ（log_access等）書き込み失敗はビジネストランザクションに影響しない
            $queryManager->execAllLogs();

        } catch (Exception|Throwable $e) {
            \Log::error('Transaction failed in UseCase', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // すべてをロールバック（TrxDB + LogDB PITR）
            foreach ($connections as $conn) {
                try {
                    DB::connection($conn)->rollBack();
                } catch (Exception $rollbackException) {
                    \Log::emergency('Rollback failed', [
                        'connection' => $conn,
                        'error' => $rollbackException->getMessage(),
                    ]);
                }
            }

            throw $e;
        }

        return $result;
    }

    /**
     * キューに積んだクエリを即座に実行する
     *
     * INSERTで採番された主キーをレスポンス生成などで直後に参照する必要がある
     * 場合にのみ使用する。executeWithTransaction()のトランザクション内で
     * 呼ばれる前提であり、失敗時はまとめてロールバックされる。
     */
    protected function flushQueue(): void
    {
        app()->make(QueryManagerInterface::class)->flush();
    }

    /**
     * アクティブな接続を取得
     *
     * sys + (trx1, trx2, ...) + (log1, log2, ...)
     *
     * @return array<string>
     */
    private function resolveActiveConnections(): array
    {
        $connections = ['sys'];

        // TrxDB接続を追加（環境変数で制御可能）
        $trxConnections = config('database.pitr.active_trx_connections', ['trx']);

        foreach ($trxConnections as $trxConn) {
            $connections[] = $trxConn;

            // 対応するLogDB接続を追加
            try {
                $logConn = ShardMapper::resolveLogConnection($trxConn);
                $connections[] = $logConn;
            } catch (\InvalidArgumentException $e) {
                // LogDBマッピングがない場合はスキップ
                \Log::warning('LogDB mapping not found for PITR', [
                    'trx_connection' => $trxConn,
                ]);
            }
        }

        return $connections;
    }
}
