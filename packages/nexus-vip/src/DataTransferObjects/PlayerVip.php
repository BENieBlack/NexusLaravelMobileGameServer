<?php

namespace NexusVip\DataTransferObjects;

/**
 * プレイヤーVIP情報DTO
 *
 * SysPlayerのVIP関連フィールドを抽出したDTO
 */
class PlayerVip
{
    public function __construct(
        private readonly int $sysPlayerId,
        private int $vipPoint,
        private float $totalPaidAmount,
    ) {}

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    /**
     * VIPポイントを取得
     */
    public function getVipPoint(): int
    {
        return $this->vipPoint;
    }

    /**
     * 累積課金額を取得
     */
    public function getTotalPaidAmount(): float
    {
        return $this->totalPaidAmount;
    }

    /**
     * VIPポイントを設定
     */
    public function setVipPoint(int $vipPoint): void
    {
        $this->vipPoint = $vipPoint;
    }

    /**
     * 累積課金額を設定
     */
    public function setTotalPaidAmount(float $totalPaidAmount): void
    {
        $this->totalPaidAmount = $totalPaidAmount;
    }

    /**
     * VIPポイントを加算
     */
    public function addVipPoint(int $points): void
    {
        $this->vipPoint += $points;
    }

    /**
     * 累積課金額を加算
     */
    public function addTotalPaidAmount(float $amount): void
    {
        $this->totalPaidAmount += $amount;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'sys_player_id' => $this->sysPlayerId,
            'vip_point' => $this->vipPoint,
            'total_paid_amount' => $this->totalPaidAmount,
        ];
    }
}
