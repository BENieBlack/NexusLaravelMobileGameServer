<?php

namespace NexusResource\DataTransferObjects;

/**
 * Item
 *
 * アイテムのデータ転送オブジェクト
 * TrxItemモデルとの変換を担当
 */
class Item
{
    /**
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId マスターアイテムID
     * @param int $freeAmount 無償アイテム数
     * @param int $paidAmount 有償アイテム数
     */
    public function __construct(
        private int $sysPlayerId,
        private string $mstItemId,
        private int $freeAmount,
        private int $paidAmount,
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
     * マスターアイテムIDを取得
     */
    public function getMstItemId(): string
    {
        return $this->mstItemId;
    }

    /**
     * 無償アイテム数を取得
     */
    public function getFreeAmount(): int
    {
        return $this->freeAmount;
    }

    /**
     * 無償アイテム数を設定
     */
    public function setFreeAmount(int $freeAmount): void
    {
        $this->freeAmount = $freeAmount;
    }

    /**
     * 有償アイテム数を取得
     */
    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    /**
     * 有償アイテム数を設定
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->paidAmount = $paidAmount;
    }

    /**
     * 合計アイテム数を取得（無償 + 有償）
     */
    public function getTotalAmount(): int
    {
        return $this->freeAmount + $this->paidAmount;
    }

    /**
     * 配列からDTOを生成
     *
     * @param  array<string, mixed>  $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sysPlayerId: $data['sys_player_id'],
            mstItemId: $data['mst_item_id'],
            freeAmount: $data['free_amount'] ?? 0,
            paidAmount: $data['paid_amount'] ?? 0,
        );
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => $this->mstItemId,
            'free_amount' => $this->freeAmount,
            'paid_amount' => $this->paidAmount,
        ];
    }
}
