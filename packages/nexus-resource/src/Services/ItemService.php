<?php

namespace NexusResource\Services;

use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DataTransferObjects\Item;

/**
 * ItemService
 *
 * アイテム残高を管理するサービス（パッケージ層）
 *
 * Responsibilities:
 * - アイテム残高の取得（findItemAmount / findItemAmounts）
 * - アイテムの加算（addItem）
 * - アイテムの消費（consumeItem）・有償優先消費ロジック
 *
 * Characteristics:
 * - DTOベースのビジネスロジック
 * - Repository Interfaceに依存（Model非依存）
 * - 純粋なビジネスルール実装
 */
class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
    ) {}

    /**
     * アイテムの所持数を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function findItemAmount(int $sysPlayerId, string $mstItemId): int
    {
        $item = $this->itemRepository->selectItem($sysPlayerId, $mstItemId);

        return $item ? $item->getTotalAmount() : 0;
    }

    /**
     * 複数アイテムの所持数を一括取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  array<string>  $mstItemIds  アイテムIDリスト
     * @return array<string, int> アイテムID => 所持数のマップ
     */
    public function findItemAmounts(int $sysPlayerId, array $mstItemIds): array
    {
        $itemDtos = $this->itemRepository->selectItemsByIds($sysPlayerId, $mstItemIds);

        $amounts = [];
        foreach ($itemDtos as $item) {
            $amounts[$item->getMstItemId()] = $item->getTotalAmount();
        }

        // 存在しないアイテムは0を設定
        foreach ($mstItemIds as $mstItemId) {
            if (! isset($amounts[$mstItemId])) {
                $amounts[$mstItemId] = 0;
            }
        }

        return $amounts;
    }

    /**
     * アイテムを加算（既存の場合は加算、新規の場合は作成）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @param  int  $freeAmount  無償アイテム数（デフォルト: 0）
     * @param  int  $paidAmount  有償アイテム数（デフォルト: 0）
     * @return Item 加算後のアイテムDTO
     */
    public function addItem(int $sysPlayerId, string $mstItemId, int $freeAmount = 0, int $paidAmount = 0): Item
    {
        // 既存のアイテムを取得
        $item = $this->itemRepository->selectItem($sysPlayerId, $mstItemId);

        if ($item) {
            // 既存アイテムがある場合は加算
            $item->setFreeAmount($item->getFreeAmount() + $freeAmount);
            $item->setPaidAmount($item->getPaidAmount() + $paidAmount);
        } else {
            // 新規アイテムを作成
            $item = new Item(
                sysPlayerId: $sysPlayerId,
                mstItemId: $mstItemId,
                freeAmount: $freeAmount,
                paidAmount: $paidAmount,
            );
        }

        // 保存
        $this->itemRepository->persistItem($item);

        return $item;
    }

    /**
     * アイテムを消費（減算）
     * 有償アイテムから優先的に消費し、不足分は無償アイテムから消費する
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  mst_item.id
     * @param  int  $amount  消費する数量
     * @return Item 消費後のアイテムDTO
     *
     * @throws \Exception 所持数が不足している場合、またはアイテムが存在しない場合
     */
    public function consumeItem(int $sysPlayerId, string $mstItemId, int $amount): Item
    {
        // 既存のアイテムを取得
        $item = $this->itemRepository->selectItem($sysPlayerId, $mstItemId);

        if (! $item) {
            throw new \Exception("Item not found: {$mstItemId}");
        }

        // 残高チェック
        $this->validateSufficientAmount($item, $amount);

        // 有償優先で消費
        $this->consumeWithPaidFirst($item, $amount);

        // 保存
        $this->itemRepository->persistItem($item);

        return $item;
    }

    /**
     * 残高が十分かチェック
     *
     * @param  Item  $item
     * @param  int  $amount
     * @return void
     *
     * @throws \Exception
     */
    private function validateSufficientAmount(Item $item, int $amount): void
    {
        $totalAmount = $item->getTotalAmount();
        if ($totalAmount < $amount) {
            throw new \Exception("Insufficient item amount. Required: {$amount}, Available: {$totalAmount}");
        }
    }

    /**
     * 有償優先で消費
     *
     * @param  Item  $item
     * @param  int  $amount
     * @return void
     */
    private function consumeWithPaidFirst(Item $item, int $amount): void
    {
        $freeAmount = $item->getFreeAmount();
        $paidAmount = $item->getPaidAmount();

        if ($amount <= $paidAmount) {
            // 有償アイテムのみで足りる場合
            $item->setPaidAmount($paidAmount - $amount);
        } else {
            // 有償アイテムを全て消費し、残りを無償アイテムから消費
            $item->setPaidAmount(0);
            $remainingAmount = $amount - $paidAmount;
            $item->setFreeAmount($freeAmount - $remainingAmount);
        }
    }
}
