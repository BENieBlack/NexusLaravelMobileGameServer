<?php

namespace NexusResource\Services;

use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DTOs\ItemDto;

/**
 * ItemWriteService
 *
 * アイテム残高の書き込み専用サービス（パッケージ層）
 * 
 * Responsibilities:
 * - アイテムの加算（addItem）
 * - アイテムの消費（consumeItem）
 * - 有償優先消費ロジック
 * 
 * Characteristics:
 * - DTOベースのビジネスロジック
 * - Repository Interfaceに依存（Model非依存）
 * - 純粋なビジネスルール実装
 */
class ItemWriteService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
    ) {
    }

    /**
     * アイテムを加算（既存の場合は加算、新規の場合は作成）
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @param int $freeAmount 無償アイテム数（デフォルト: 0）
     * @param int $paidAmount 有償アイテム数（デフォルト: 0）
     * @return ItemDto 加算後のアイテムDTO
     */
    public function addItem(int $sysPlayerId, string $mstItemId, int $freeAmount = 0, int $paidAmount = 0): ItemDto
    {
        // 既存のアイテムを取得
        $itemDto = $this->itemRepository->findItem($sysPlayerId, $mstItemId);

        if ($itemDto) {
            // 既存アイテムがある場合は加算
            $itemDto->setFreeAmount($itemDto->getFreeAmount() + $freeAmount);
            $itemDto->setPaidAmount($itemDto->getPaidAmount() + $paidAmount);
        } else {
            // 新規アイテムを作成
            $itemDto = new ItemDto(
                sysPlayerId: $sysPlayerId,
                mstItemId: $mstItemId,
                freeAmount: $freeAmount,
                paidAmount: $paidAmount,
            );
        }

        // 保存
        $this->itemRepository->saveItem($itemDto);

        return $itemDto;
    }

    /**
     * アイテムを消費（減算）
     * 有償アイテムから優先的に消費し、不足分は無償アイテムから消費する
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId mst_item.id
     * @param int $amount 消費する数量
     * @return ItemDto 消費後のアイテムDTO
     * @throws \Exception 所持数が不足している場合、またはアイテムが存在しない場合
     */
    public function consumeItem(int $sysPlayerId, string $mstItemId, int $amount): ItemDto
    {
        // 既存のアイテムを取得
        $itemDto = $this->itemRepository->findItem($sysPlayerId, $mstItemId);

        if (!$itemDto) {
            throw new \Exception("Item not found: {$mstItemId}");
        }

        // 残高チェック
        $this->validateSufficientAmount($itemDto, $amount, $mstItemId);

        // 有償優先で消費
        $this->consumeWithPaidFirst($itemDto, $amount);

        // 保存
        $this->itemRepository->saveItem($itemDto);

        return $itemDto;
    }

    /**
     * 残高が十分かチェック
     *
     * @param ItemDto $itemDto
     * @param int $amount
     * @param string $mstItemId
     * @return void
     * @throws \Exception
     */
    private function validateSufficientAmount(ItemDto $itemDto, int $amount, string $mstItemId): void
    {
        $totalAmount = $itemDto->getTotalAmount();
        if ($totalAmount < $amount) {
            throw new \Exception("Insufficient item amount. Required: {$amount}, Available: {$totalAmount}");
        }
    }

    /**
     * 有償優先で消費
     *
     * @param ItemDto $itemDto
     * @param int $amount
     * @return void
     */
    private function consumeWithPaidFirst(ItemDto $itemDto, int $amount): void
    {
        $freeAmount = $itemDto->getFreeAmount();
        $paidAmount = $itemDto->getPaidAmount();

        if ($amount <= $paidAmount) {
            // 有償アイテムのみで足りる場合
            $itemDto->setPaidAmount($paidAmount - $amount);
        } else {
            // 有償アイテムを全て消費し、残りを無償アイテムから消費
            $itemDto->setPaidAmount(0);
            $remainingAmount = $amount - $paidAmount;
            $itemDto->setFreeAmount($freeAmount - $remainingAmount);
        }
    }
}
