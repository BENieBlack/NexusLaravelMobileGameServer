<?php

namespace App\Domain\Item\Services;

use App\Models\Trx\TrxItem;
use App\Repositories\Trx\TrxItemRepository;
use App\Persistence\ApiSession;

/**
 * ItemService
 *
 * アイテム管理のビジネスロジックを担当するサービス
 */
class ItemService
{
    public function __construct(
        private readonly TrxItemRepository $trxItemRepository,
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * アイテムを加算（既存の場合は加算、新規の場合は作成）
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @param int $freeAmount 無償アイテム数（デフォルト: 0）
     * @param int $paidAmount 有償アイテム数（デフォルト: 0）
     * @return void
     */
    public function addItem(int $sysPlayerId, string $mstItemId, int $freeAmount = 0, int $paidAmount = 0): void
    {

        // 既存のアイテムを取得
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        if ($trxItem) {
            // 既存アイテムがある場合は加算
            $trxItem->setFreeAmount($trxItem->getFreeAmount() + $freeAmount);
            $trxItem->setPaidAmount($trxItem->getPaidAmount() + $paidAmount);
        } else {
            // 新規アイテムを作成
            $trxItem = new TrxItem([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'free_amount' => $freeAmount,
                'paid_amount' => $paidAmount,
            ]);
            $trxItem->exists = false; // INSERT として認識
        }

        // setModelで内部キューに溜め込む
        $this->trxItemRepository->setModel($trxItem);
    }

    /**
     * アイテムを消費（減算）
     * 有償アイテムから優先的に消費し、不足分は無償アイテムから消費する
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId mst_item.id
     * @param int $amount 消費する数量
     * @return TrxItem 消費後のアイテムデータ
     * @throws \Exception 所持数が不足している場合
     */
    public function consumeItem(int $sysPlayerId, string $mstItemId, int $amount): TrxItem
    {

        // 既存のアイテムを取得
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        if (!$trxItem) {
            throw new \Exception("Item not found: {$mstItemId}");
        }

        $totalAmount = $trxItem->getTotalAmount();
        if ($totalAmount < $amount) {
            throw new \Exception("Insufficient item amount. Required: {$amount}, Available: {$totalAmount}");
        }

        // 有償アイテムから優先的に消費
        $freeAmount = $trxItem->getFreeAmount();
        $paidAmount = $trxItem->getPaidAmount();
        
        if ($amount <= $paidAmount) {
            // 有償アイテムのみで足りる場合
            $trxItem->setPaidAmount($paidAmount - $amount);
        } else {
            // 有償アイテムを全て消費し、残りを無償アイテムから消費
            $trxItem->setPaidAmount(0);
            $remainingAmount = $amount - $paidAmount;
            $trxItem->setFreeAmount($freeAmount - $remainingAmount);
        }

        // setModelで内部キューに溜め込む（トランザクションコミット時にDB反映）
        $this->trxItemRepository->setModel($trxItem);

        // 更新後のアイテムデータを返す
        return $trxItem;
    }

    /**
     * アイテムの所持数を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function getItemAmount(int $sysPlayerId, string $mstItemId): int
    {

        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $trxItem ? $trxItem->getTotalAmount() : 0;
    }
}
