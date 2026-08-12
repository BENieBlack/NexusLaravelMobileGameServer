<?php

namespace NexusVip\DTOs;

/**
 * VIP情報レスポンスDTO
 *
 * Note: vip_levelはサーバー側で計算して返す
 * クライアント側でも vip_point と mst_vip_level から再計算可能
 */
class VipInfoDto
{
    public function __construct(
        private readonly int $vipPoint,
        private readonly int $vipLevel,
        private readonly ?int $pointsToNextLevel,
        private readonly ?int $nextLevel,
        private readonly VipBenefit $benefits,
        private readonly float $totalPaidAmount,
    ) {}

    /**
     * VIPポイントを取得
     */
    public function getVipPoint(): int
    {
        return $this->vipPoint;
    }

    /**
     * VIPレベルを取得
     */
    public function getVipLevel(): int
    {
        return $this->vipLevel;
    }

    /**
     * 次のレベルまでのポイントを取得
     */
    public function getPointsToNextLevel(): ?int
    {
        return $this->pointsToNextLevel;
    }

    /**
     * 次のレベルを取得
     */
    public function getNextLevel(): ?int
    {
        return $this->nextLevel;
    }

    /**
     * 特典を取得
     */
    public function getBenefits(): VipBenefit
    {
        return $this->benefits;
    }

    /**
     * 累計課金額を取得
     */
    public function getTotalPaidAmount(): float
    {
        return $this->totalPaidAmount;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'vip_point' => $this->vipPoint,
            'vip_level' => $this->vipLevel,  // サーバー側で計算
            'points_to_next_level' => $this->pointsToNextLevel,
            'next_level' => $this->nextLevel,
            'benefits' => $this->benefits->toArray(),
            'total_paid_amount' => $this->totalPaidAmount,
        ];
    }
}
