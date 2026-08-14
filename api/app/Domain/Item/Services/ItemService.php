<?php

namespace App\Domain\Item\Services;

use App\Models\Trx\TrxItem;

/**
 * ItemService (Facade)
 *
 * アイテム管理サービスのFacade
 *
 * このクラスは後方互換性のために維持されています。
 * 新しいコードでは、ReadServiceまたはWriteServiceを直接使用してください。
 *
 * Design Pattern: Facade Pattern
 * - Delegates read operations to ReadService
 * - Delegates write operations to WriteService
 *
 * @deprecated 新規コードではReadService/WriteServiceを直接使用してください
 */
class ItemService
{
    public function __construct(
        private readonly ItemReadService $itemReadService,
        private readonly ItemWriteService $itemWriteService,
    ) {}

    /**
     * アイテムを加算（既存の場合は加算、新規の場合は作成）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @param  int  $freeAmount  無償アイテム数（デフォルト: 0）
     * @param  int  $paidAmount  有償アイテム数（デフォルト: 0）
     */
    public function addItem(int $sysPlayerId, string $mstItemId, int $freeAmount = 0, int $paidAmount = 0): void
    {
        $this->itemWriteService->addItem($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);
    }

    /**
     * アイテムを消費（減算）
     * 有償アイテムから優先的に消費し、不足分は無償アイテムから消費する
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  mst_item.id
     * @param  int  $amount  消費する数量
     * @return TrxItem 消費後のアイテムデータ
     *
     * @throws \Exception 所持数が不足している場合
     */
    public function consumeItem(int $sysPlayerId, string $mstItemId, int $amount): TrxItem
    {
        return $this->itemWriteService->consumeItem($sysPlayerId, $mstItemId, $amount);
    }

    /**
     * アイテムの所持数を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function findItemAmount(int $sysPlayerId, string $mstItemId): int
    {
        return $this->itemReadService->findItemAmount($sysPlayerId, $mstItemId);
    }
}
