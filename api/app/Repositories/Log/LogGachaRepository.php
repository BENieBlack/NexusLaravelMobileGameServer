<?php

namespace App\Repositories\Log;

use App\Models\Log\LogGacha;
use App\Utilities\Clock;
use Illuminate\Support\Collection;

/**
 * LogGachaRepository
 *
 * ガチャログを管理するRepository
 */
class LogGachaRepository extends _BaseLogRepository
{
    protected string $modelClass = LogGacha::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * ガチャログを記録（Unit of Work パターン使用）
     *
     * @param string $uniqueRequestId リクエスト一意ID
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstGachaId ガチャマスターID
     * @param array|null $result ガチャ結果
     * @return void
     */
    public function createGachaLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $mstGachaId,
        ?array $result = null
    ): void {
        $model = new LogGacha([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'mst_gacha_id' => $mstGachaId,
            'result' => $result,
            'system_at' => Clock::now(),
            'created_at' => Clock::now(),
        ]);

        // 通常ログとして登録
        $this->setModel($model);
    }

    /**
     * 特定ガチャのログを取得
     *
     * @param string $mstGachaId ガチャマスターID
     * @return Collection<int, LogGacha>
     */
    public function findAllByMstGachaId(string $mstGachaId): Collection
    {
        return $this->queryOrMemory()
            ->where('mst_gacha_id', $mstGachaId)
            ->sortByDesc('system_at')
            ->values();
    }

    /**
     * リクエストIDでガチャログを取得
     *
     * @param string $uniqueRequestId
     * @return LogGacha|null
     */
    public function findByUniqueRequestId(string $uniqueRequestId): ?LogGacha
    {
        return $this->queryOrMemory()
            ->where('unique_request_id', $uniqueRequestId)
            ->first();
    }
}
