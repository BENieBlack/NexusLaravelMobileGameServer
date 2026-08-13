<?php

namespace NexusVip\Services;

use NexusVip\DTOs\PlayerVipDto;
use NexusVip\Events\VipLevelUpEvent;
use NexusVip\Exceptions\InvalidVipPointException;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;
use NexusVip\ValueObjects\VipConfig;

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
        protected VipConfig $config,
    ) {}

    /**
     * VIPポイントを付与
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $points  VIPポイント
     * @param  string  $reason  理由（purchase, manual_adjustment, campaign）
     * @param  array  $metadata  メタデータ
     *
     * @throws InvalidVipPointException
     */
    public function addPoints(
        int $sysPlayerId,
        int $points,
        string $reason,
        array $metadata = []
    ): PlayerVipDto {
        if ($points <= 0) {
            throw new InvalidVipPointException("Points must be positive, got: {$points}");
        }

        // プレイヤー情報を取得
        $playerVip = $this->playerVipRepository->selectVipInfoById($sysPlayerId);

        if ($playerVip === null) {
            throw new InvalidVipPointException("Player not found: {$sysPlayerId}");
        }

        $beforePoint = $playerVip->getVipPoint();

        // 変更前のVIPレベルを計算
        $beforeLevel = $this->vipLevelService->calculateLevel($beforePoint);

        // ポイント加算
        $playerVip->addVipPoint($points);

        // 課金額を累積（metadataに含まれている場合）
        if (isset($metadata['purchase_amount_jpy'])) {
            $playerVip->addTotalPaidAmount($metadata['purchase_amount_jpy']);
        }

        // 変更後のVIPレベルを計算
        $afterLevel = $this->vipLevelService->calculateLevel($playerVip->getVipPoint());

        // Repository に保存（Unit of Workパターン）
        $this->playerVipRepository->saveVipInfo($playerVip);

        // ログ記録（設定で有効な場合）
        if ($this->config->isPointLogEnabled()) {
            $this->logVipPointChange(
                uniqueRequestId: $metadata['unique_request_id'] ?? uniqid('vip_', true),
                sysPlayerId: $sysPlayerId,
                beforeLevel: $beforeLevel,
                afterLevel: $afterLevel,
                beforePoint: $beforePoint,
                afterPoint: $playerVip->getVipPoint(),
                pointDiff: $points,
                reason: $reason,
                metadata: $metadata
            );
        }

        // レベルアップ時のイベント発火（設定で有効な場合）
        if ($afterLevel > $beforeLevel && $this->config->isLevelUpEventEnabled()) {
            // 複数レベルアップした場合は、各レベルの報酬を全て取得
            $allRewards = [];
            for ($level = $beforeLevel + 1; $level <= $afterLevel; $level++) {
                $levelRewards = $this->vipRewardService->getRewardsArray($level);
                $allRewards = array_merge($allRewards, $levelRewards);
            }

            event(new VipLevelUpEvent($sysPlayerId, $beforeLevel, $afterLevel, $allRewards));
        }

        return $playerVip;
    }

    /**
     * VIPポイント変動ログを記録
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
     */
    public function getPlayerVipInfo(int $sysPlayerId): ?PlayerVipDto
    {
        return $this->playerVipRepository->selectVipInfoById($sysPlayerId);
    }
}
