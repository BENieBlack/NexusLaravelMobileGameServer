<?php

namespace App\Repositories\Log;

use App\Models\Log\LogAccess;
use App\Utilities\Clock;

/**
 * LogAccessRepository
 *
 * アクセスログを管理するRepository
 * リクエスト/レスポンスの全情報を記録
 */
class LogAccessRepository extends _BaseLogRepository
{
    protected string $modelClass = LogAccess::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * アクセスログを記録（Unit of Work パターン使用）
     *
     * @param string $uniqueRequestId リクエスト一意ID
     * @param int $sysPlayerId プレイヤーID
     * @param string $method HTTPメソッド
     * @param string $endpoint エンドポイント
     * @param array|null $requestHeader リクエストヘッダー
     * @param array|null $requestBody リクエストボディ
     * @param array|null $responseHeader レスポンスヘッダー
     * @param array|null $responseBody レスポンスボディ
     * @param int $statusCode ステータスコード
     * @return void
     */
    public function createAccessLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $method,
        string $endpoint,
        ?array $requestHeader = null,
        ?array $requestBody = null,
        ?array $responseHeader = null,
        ?array $responseBody = null,
        int $statusCode = 200
    ): void {
        $model = new LogAccess([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'method' => $method,
            'endpoint' => $endpoint,
            'request_header' => $requestHeader,
            'request_body' => $requestBody,
            'response_header' => $responseHeader,
            'response_body' => $responseBody,
            'status_code' => $statusCode,
            'system_at' => Clock::now(),
            'created_at' => Clock::now(),
        ]);

        // 通常ログとして登録
        $this->setModel($model);
    }

    /**
     * リクエストIDでアクセスログを取得
     *
     * @param string $uniqueRequestId
     * @return LogAccess|null
     */
    public function findByUniqueRequestId(string $uniqueRequestId): ?LogAccess
    {
        return $this->queryOrMemory()
            ->where('unique_request_id', $uniqueRequestId)
            ->first();
    }
}
