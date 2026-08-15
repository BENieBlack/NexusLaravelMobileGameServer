<?php

namespace NexusBilling\DataTransferObjects;

/**
 * DiamondBalance
 *
 * ダイヤモンド残高のデータ転送オブジェクト
 */
class DiamondBalance
{
    /**
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @param int $paidAmount 有償ダイヤモンド数
     * @param int $freeAmount 無償ダイヤモンド数
     */
    public function __construct(
        private int $sysPlayerId,
        private string $platform,
        private int $paidAmount,
        private int $freeAmount,
    ) {
    }

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    /**
     * プラットフォームを取得
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * 有償ダイヤモンド数を取得
     */
    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    /**
     * 有償ダイヤモンド数を設定
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->paidAmount = $paidAmount;
    }

    /**
     * 無償ダイヤモンド数を取得
     */
    public function getFreeAmount(): int
    {
        return $this->freeAmount;
    }

    /**
     * 無償ダイヤモンド数を設定
     */
    public function setFreeAmount(int $freeAmount): void
    {
        $this->freeAmount = $freeAmount;
    }

    /**
     * 合計ダイヤモンド数を取得
     */
    public function getTotalAmount(): int
    {
        return $this->paidAmount + $this->freeAmount;
    }

    /**
     * 配列からDTOを生成
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sysPlayerId: $data['sys_player_id'],
            platform: $data['platform'],
            paidAmount: $data['paid_amount'] ?? 0,
            freeAmount: $data['free_amount'] ?? 0,
        );
    }

    /**
     * 配列に変換
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'paid_amount' => $this->paidAmount,
            'free_amount' => $this->freeAmount,
            'total_amount' => $this->getTotalAmount(),
        ];
    }
}
