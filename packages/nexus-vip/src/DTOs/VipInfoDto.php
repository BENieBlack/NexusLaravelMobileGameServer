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
        public readonly int $vipPoint,
        public readonly int $vipLevel,
        public readonly ?int $pointsToNextLevel,
        public readonly ?int $nextLevel,
        public readonly VipBenefitDto $benefits,
        public readonly float $totalPaidAmount,
    ) {
    }

    /**
     * 配列に変換
     *
     * @return array
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
