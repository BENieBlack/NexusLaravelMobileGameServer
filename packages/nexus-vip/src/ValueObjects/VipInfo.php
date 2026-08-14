<?php

namespace NexusVip\ValueObjects;

/**
 * VIP情報 Value Object
 *
 * プレイヤーのVIP状態をまとめて保持する不変オブジェクト。
 *
 * Note: vip_levelはサーバー側で vip_point から算出して返す
 * クライアント側でも vip_point と mst_vip_level から再計算可能
 */
final class VipInfo
{
    /**
     * @param  int  $vipPoint  累積VIPポイント
     * @param  int  $vipLevel  VIPレベル（vip_pointから算出）
     * @param  int|null  $pointsToNextLevel  次のレベルまでのポイント（最高レベルの場合null）
     * @param  int|null  $nextLevel  次のレベル（最高レベルの場合null）
     * @param  VipBenefit  $benefits  現在のVIPレベルの特典
     * @param  float  $totalPaidAmount  累計課金額
     *
     * @throws \InvalidArgumentException 値が不正な場合
     */
    public function __construct(
        private readonly int $vipPoint,
        private readonly int $vipLevel,
        private readonly ?int $pointsToNextLevel,
        private readonly ?int $nextLevel,
        private readonly VipBenefit $benefits,
        private readonly float $totalPaidAmount,
    ) {
        if ($vipPoint < 0) {
            throw new \InvalidArgumentException("累積VIPポイントは0以上である必要があります: {$vipPoint}");
        }

        if ($vipLevel < 0) {
            throw new \InvalidArgumentException("VIPレベルは0以上である必要があります: {$vipLevel}");
        }

        if ($totalPaidAmount < 0) {
            throw new \InvalidArgumentException("累計課金額は0以上である必要があります: {$totalPaidAmount}");
        }
    }

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
    public function calcPointsToNextLevel(): ?int
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
    public function findBenefits(): VipBenefit
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
     * 最高レベルに到達しているか
     */
    public function isMaxLevel(): bool
    {
        return $this->nextLevel === null;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->vipPoint === $other->vipPoint
            && $this->vipLevel === $other->vipLevel
            && $this->pointsToNextLevel === $other->pointsToNextLevel
            && $this->nextLevel === $other->nextLevel
            && $this->totalPaidAmount === $other->totalPaidAmount
            && $this->benefits->equals($other->benefits);
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
