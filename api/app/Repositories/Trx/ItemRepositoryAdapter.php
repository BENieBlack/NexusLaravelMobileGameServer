<?php

namespace App\Repositories\Trx;

use App\Adapters\Item\ItemAdapter;
use App\Models\Trx\TrxItem;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DataTransferObjects\Item;

/**
 * ItemRepositoryAdapter
 *
 * ItemRepositoryInterfaceの実装クラス
 * TrxItemモデル ↔ Item の変換を担当
 */
class ItemRepositoryAdapter implements ItemRepositoryInterface
{
    public function __construct(
        private readonly TrxItemRepository $trxItemRepository,
    ) {}

    /**
     * アイテムを検索
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     */
    public function selectItem(int $sysPlayerId, string $mstItemId): ?Item
    {
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $trxItem ? ItemAdapter::toDto($trxItem) : null;
    }

    /**
     * アイテムを保存（新規作成 or 更新）
     */
    public function persistItem(Item $item): void
    {
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $item->getSysPlayerId())
            ->where('mst_item_id', $item->getMstItemId())
            ->first();

        if ($trxItem) {
            // 既存レコードを更新
            $trxItem->setFreeAmount($item->getFreeAmount());
            $trxItem->setPaidAmount($item->getPaidAmount());
        } else {
            // 新規レコードを作成
            $trxItem = new TrxItem([
                'sys_player_id' => $item->getSysPlayerId(),
                'mst_item_id' => $item->getMstItemId(),
                'free_amount' => $item->getFreeAmount(),
                'paid_amount' => $item->getPaidAmount(),
            ]);
            $trxItem->exists = false; // INSERT として認識
        }

        // setModelで内部キューに溜め込む（トランザクションコミット時にDB反映）
        $this->trxItemRepository->setModel($trxItem);
    }

    /**
     * 複数アイテムを一括取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  array<string>  $mstItemIds  アイテムIDリスト
     * @return array<Item>
     */
    public function selectItemsByIds(int $sysPlayerId, array $mstItemIds): array
    {
        $trxItems = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->whereIn('mst_item_id', $mstItemIds)
            ->get();

        return $trxItems->map(fn (TrxItem $trxItem) => ItemAdapter::toDto($trxItem))->all();
    }
}
