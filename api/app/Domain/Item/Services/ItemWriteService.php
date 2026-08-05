<?php

namespace App\Domain\Item\Services;

use App\Models\Trx\TrxItem;
use App\Repositories\Trx\TrxItemRepository;

/**
 * ItemWriteService
 *
 * アイテム残高の書き込み専用サービス
 * 
 * Responsibilities:
 * - アイテムの加算（addItem）
 * - アイテムの消費（consumeItem）
 * - 有償優先消費ロジック
 * 
 * Characteristics:
 * - Write operations (state changes)
 * - Command operations only
 * - Transactional operations
 * 
 * Paid-first consumption priority:
 * 1. Consume from paid_amount first
 * 2. If paid_amount is insufficient, consume remaining from free_amount
 */
class ItemWriteService
{
    public function __construct(
        private readonly TrxItemRepository $trxItemRepository,
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
        $trxItem = $this->findItem($sysPlayerId, $mstItemId);

        if ($trxItem) {
            // 既存アイテムがある場合は加算
            $this->incrementItem($trxItem, $freeAmount, $paidAmount);
        } else {
            // 新規アイテムを作成
            $trxItem = $this->createNewItem($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);
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
        $trxItem = $this->findItem($sysPlayerId, $mstItemId);

        if (!$trxItem) {
            throw new \Exception("Item not found: {$mstItemId}");
        }

        // 残高チェック
        $this->validateSufficientAmount($trxItem, $amount, $mstItemId);

        // 有償優先で消費
        $this->consumeWithPaidFirst($trxItem, $amount);

        // setModelで内部キューに溜め込む（トランザクションコミット時にDB反映）
        $this->trxItemRepository->setModel($trxItem);

        // 更新後のアイテムデータを返す
        return $trxItem;
    }

    /**
     * アイテムを取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @return TrxItem|null
     */
    private function findItem(int $sysPlayerId, string $mstItemId): ?TrxItem
    {
        return TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();
    }

    /**
     * 既存アイテムに加算
     *
     * @param TrxItem $trxItem
     * @param int $freeAmount
     * @param int $paidAmount
     * @return void
     */
    private function incrementItem(TrxItem $trxItem, int $freeAmount, int $paidAmount): void
    {
        $trxItem->setFreeAmount($trxItem->getFreeAmount() + $freeAmount);
        $trxItem->setPaidAmount($trxItem->getPaidAmount() + $paidAmount);
    }

    /**
     * 新規アイテムを作成
     *
     * @param int $sysPlayerId
     * @param string $mstItemId
     * @param int $freeAmount
     * @param int $paidAmount
     * @return TrxItem
     */
    private function createNewItem(int $sysPlayerId, string $mstItemId, int $freeAmount, int $paidAmount): TrxItem
    {
        $trxItem = new TrxItem([
            'sys_player_id' => $sysPlayerId,
            'mst_item_id' => $mstItemId,
            'free_amount' => $freeAmount,
            'paid_amount' => $paidAmount,
        ]);
        $trxItem->exists = false; // INSERT として認識

        return $trxItem;
    }

    /**
     * 残高が十分かチェック
     *
     * @param TrxItem $trxItem
     * @param int $amount
     * @param string $mstItemId
     * @return void
     * @throws \Exception
     */
    private function validateSufficientAmount(TrxItem $trxItem, int $amount, string $mstItemId): void
    {
        $totalAmount = $trxItem->getTotalAmount();
        if ($totalAmount < $amount) {
            throw new \Exception("Insufficient item amount. Required: {$amount}, Available: {$totalAmount}");
        }
    }

    /**
     * 有償優先で消費
     *
     * @param TrxItem $trxItem
     * @param int $amount
     * @return void
     */
    private function consumeWithPaidFirst(TrxItem $trxItem, int $amount): void
    {
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
    }
}
