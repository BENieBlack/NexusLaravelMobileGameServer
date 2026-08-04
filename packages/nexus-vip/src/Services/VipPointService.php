<?php

namespace NexusVip\Services;

use App\Models\Sys\SysPlayer;
use NexusVip\Events\VipLevelUpEvent;
use NexusVip\Exceptions\InvalidVipPointException;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;

/**
 * VIPポイントサービス
 * 
 * VIPポイントの計算と付与を担当
 */
class VipPointService
{
    public function __construct(
        protected PlayerVipRepositoryInterface $playerVipRepository,
        protected VipPointLogRepositoryInterface $vipPointLogRepository,
        protected VipLevelService $vipLevelService,
        protected VipRewardService $vipRewardService,
    ) {
    }

    /**
     * VIPポイントを付与
     *
     * @param int $sysPlayerId プレイヤーID
     * @param int $points VIPポイント
     * @param string $reason 理由（purchase, manual_adjustment, campaign）
     * @param array $metadata メタデータ
     * @return SysPlayer
     * @throws InvalidVipPointException
     */
    public function addPoints(
        int $sysPlayerId,
        int $points,
        string $reason,
        array $metadata = []
    ): SysPlayer {
        if ($points <= 0) {
            throw new InvalidVipPointException("Points must be positive, got: {$points}");
        }

        // プレイヤー情報を取得
        $player = $this->playerVipRepository->findById($sysPlayerId);
        
        if ($player === null) {
            throw new InvalidVipPointException("Player not found: {$sysPlayerId}");
        }
        
        $beforePoint = $player->getVipPoint();
        
        // 変更前のVIPレベルを計算
        $beforeLevel = $this->vipLevelService->calculateLevel($beforePoint);
        
        // ポイント加算
        $player->addVipPoint($points);
        
        // 課金額を累積（metadataに含まれている場合）
        if (isset($metadata['purchase_amount_jpy'])) {
            $player->addTotalPaidAmount($metadata['purchase_amount_jpy']);
        }
        
        // 変更後のVIPレベルを計算
        $afterLevel = $this->vipLevelService->calculateLevel($player->getVipPoint());
        
        // Repository に登録（Unit of Workパターン）
        // Note: vip_level カラムは保存しない（クライアント側で計算）
        $this->playerVipRepository->setModel($player);
        
        // ログ記録（設定で有効な場合）
        if (config('vip.enable_point_log', true)) {
            $this->logVipPointChange(
                uniqueRequestId: $metadata['unique_request_id'] ?? uniqid('vip_', true),
                sysPlayerId: $sysPlayerId,
                beforeLevel: $beforeLevel,
                afterLevel: $afterLevel,
                beforePoint: $beforePoint,
                afterPoint: $player->getVipPoint(),
                pointDiff: $points,
                reason: $reason,
                metadata: $metadata
            );
        }
        
        // レベルアップ時のイベント発火（設定で有効な場合）
        if ($afterLevel > $beforeLevel && config('vip.enable_level_up_event', true)) {
            // 複数レベルアップした場合は、各レベルの報酬を全て取得
            $allRewards = [];
            for ($level = $beforeLevel + 1; $level <= $afterLevel; $level++) {
                $levelRewards = $this->vipRewardService->getRewardsArray($level);
                $allRewards = array_merge($allRewards, $levelRewards);
            }
            
            event(new VipLevelUpEvent($sysPlayerId, $beforeLevel, $afterLevel, $allRewards));
        }
        
        return $player;
    }

    /**
     * VIPポイント変動ログを記録
     *
     * @param string $uniqueRequestId
     * @param int $sysPlayerId
     * @param int $beforeLevel
     * @param int $afterLevel
     * @param int $beforePoint
     * @param int $afterPoint
     * @param int $pointDiff
     * @param string $reason
     * @param array $metadata
     * @return void
     */
    protected function logVipPointChange(
        string $uniqueRequestId,
        int $sysPlayerId,
        int $beforeLevel,
        int $afterLevel,
        int $beforePoint,
        int $afterPoint,
        int $pointDiff,
        string $reason,
        array $metadata
    ): void {
        $this->vipPointLogRepository->log(
            uniqueRequestId: $uniqueRequestId,
            sysPlayerId: $sysPlayerId,
            beforeLevel: $beforeLevel,
            afterLevel: $afterLevel,
            beforePoint: $beforePoint,
            afterPoint: $afterPoint,
            pointDiff: $pointDiff,
            reason: $reason,
            metadata: $metadata
        );
    }

    /**
     * プレイヤーのVIP情報を取得
     *
     * @param int $sysPlayerId
     * @return SysPlayer|null
     */
    public function getPlayerVipInfo(int $sysPlayerId): ?SysPlayer
    {
        return $this->playerVipRepository->findById($sysPlayerId);
    }
}
