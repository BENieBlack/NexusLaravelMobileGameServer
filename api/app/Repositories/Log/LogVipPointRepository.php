<?php

namespace App\Repositories\Log;

use App\Models\Log\LogVipPoint;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusVip\Repositories\VipPointLogRepositoryInterface;

/**
 * LogVipPointRepository
 *
 * VIPポイント変動ログを管理するRepository
 *
 * @extends _BaseLogRepository<LogVipPoint>
 */
class LogVipPointRepository extends _BaseLogRepository implements VipPointLogRepositoryInterface
{
    protected string $modelClass = LogVipPoint::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * VIPポイント変動ログを記録
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $uniqueRequestId,
        int $sysPlayerId,
        int $beforeLevel,
        int $afterLevel,
        int $beforePoint,
        int $afterPoint,
        int $pointDiff,
        string $reason,
        array $metadata = []
    ): void {
        $model = new LogVipPoint([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'before_vip_level' => $beforeLevel,
            'after_vip_level' => $afterLevel,
            'before_vip_point' => $beforePoint,
            'after_vip_point' => $afterPoint,
            'point_diff' => $pointDiff,
            'reason' => $reason,
            'purchase_amount' => $metadata['purchase_amount'] ?? null,
            'currency_code' => $metadata['currency_code'] ?? null,
            'mst_in_app_purchase_id' => $metadata['mst_in_app_purchase_id'] ?? null,
            'system_at' => ClockUtility::now(),
        ]);

        // 通常ログとして登録
        $this->setModel($model);
    }

    /**
     * プレイヤーのVIPポイント履歴を取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function selectHistory(int $sysPlayerId, int $limit = 100): array
    {
        return $this->queryOrMemory()
            ->where('sys_player_id', $sysPlayerId)
            ->sortByDesc('system_at')
            ->take($limit)
            ->map(function (LogVipPoint $log) {
                return [
                    'id' => $log->id,
                    'before_vip_level' => $log->getBeforeVipLevel(),
                    'after_vip_level' => $log->getAfterVipLevel(),
                    'before_vip_point' => $log->getBeforeVipPoint(),
                    'after_vip_point' => $log->getAfterVipPoint(),
                    'point_diff' => $log->getPointDiff(),
                    'reason' => $log->getReason(),
                    'purchase_amount' => $log->getPurchaseAmount(),
                    'currency_code' => $log->getCurrencyCode(),
                    'mst_in_app_purchase_id' => $log->getMstInAppPurchaseId(),
                    'system_at' => $log->getSystemAt(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * リクエストIDでログを取得
     */
    public function selectByUniqueRequestId(string $uniqueRequestId): ?LogVipPoint
    {
        return $this->queryOrMemory()
            ->where('unique_request_id', $uniqueRequestId)
            ->first();
    }

    /**
     * 特定期間のVIPポイント変動ログを取得
     *
     * 日時は 'Y-m-d H:i:s' 形式の文字列で受け取る。
     * 固定長のため辞書順比較が時系列順比較と一致する。
     *
     * @param  string  $startDate  'Y-m-d H:i:s'
     * @param  string  $endDate  'Y-m-d H:i:s'
     * @return CustomCollection<int, LogVipPoint>
     */
    public function selectByPeriod(
        int $sysPlayerId,
        string $startDate,
        string $endDate
    ): CustomCollection {
        return $this->queryOrMemory()
            ->where('sys_player_id', $sysPlayerId)
            ->filter(function (LogVipPoint $log) use ($startDate, $endDate) {
                $systemAt = $log->getSystemAt();

                return $systemAt >= $startDate && $systemAt <= $endDate;
            })
            ->sortByDesc('system_at')
            ->values();
    }

    /**
     * 変更理由で絞り込んでログを取得
     *
     * @return CustomCollection<int, LogVipPoint>
     */
    public function selectByReason(int $sysPlayerId, string $reason, int $limit = 100): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('sys_player_id', $sysPlayerId)
            ->where('reason', $reason)
            ->sortByDesc('system_at')
            ->take($limit)
            ->values();
    }
}
