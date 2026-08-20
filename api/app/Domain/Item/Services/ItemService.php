<?php

namespace App\Domain\Item\Services;

use App\Models\Trx\TrxItem;
use NexusResource\Services\ItemService as PackageItemService;

/**
 * ItemService (Domain層ラッパー)
 *
 * パッケージ層のItemサービスをラップし、DTO ↔ Model変換を担当する。
 *
 * Responsibilities:
 * - パッケージ層Serviceへの委譲
 * - Item(DTO) → TrxItem(Model) への変換
 *
 * Note: ビジネスロジックはパッケージ層（NexusResource\Services\ItemService）に存在する。
 */
class ItemService
{
    public function __construct(
        private readonly PackageItemService $packageItemService,
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
        $this->packageItemService->addItem($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);
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
        $item = $this->packageItemService->consumeItem($sysPlayerId, $mstItemId, $amount);

        // DTOからTrxItemを再取得（既存の利用箇所との互換性のため）
        // Note: 将来的にはDTOを直接返すようにリファクタリング推奨
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $item->getSysPlayerId())
            ->where('mst_item_id', $item->getMstItemId())
            ->first();

        if (! $trxItem) {
            throw new \Exception("Item not found after consumption: {$mstItemId}");
        }

        // 消費結果はUnitOfWorkのキューに積まれただけでDBには未反映のため、
        // 再取得したモデルにはDTOの最新値を反映して返す
        $trxItem->setFreeAmount($item->getFreeAmount());
        $trxItem->setPaidAmount($item->getPaidAmount());

        return $trxItem;
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
        return $this->packageItemService->findItemAmount($sysPlayerId, $mstItemId);
    }
}
