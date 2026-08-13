<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxItem;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DTOs\ItemDto;

/**
 * ItemRepositoryAdapter
 *
 * ItemRepositoryInterfaceの実装クラス
 * TrxItemモデル ↔ ItemDto の変換を担当
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
    public function selectItem(int $sysPlayerId, string $mstItemId): ?ItemDto
    {
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $trxItem ? $this->modelToDto($trxItem) : null;
    }

    /**
     * アイテムを保存（新規作成 or 更新）
     */
    public function persistItem(ItemDto $itemDto): void
    {
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $itemDto->getSysPlayerId())
            ->where('mst_item_id', $itemDto->getMstItemId())
            ->first();

        if ($trxItem) {
            // 既存レコードを更新
            $trxItem->setFreeAmount($itemDto->getFreeAmount());
            $trxItem->setPaidAmount($itemDto->getPaidAmount());
        } else {
            // 新規レコードを作成
            $trxItem = new TrxItem([
                'sys_player_id' => $itemDto->getSysPlayerId(),
                'mst_item_id' => $itemDto->getMstItemId(),
                'free_amount' => $itemDto->getFreeAmount(),
                'paid_amount' => $itemDto->getPaidAmount(),
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
     * @return array<ItemDto>
     */
    public function selectItemsByIds(int $sysPlayerId, array $mstItemIds): array
    {
        $trxItems = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->whereIn('mst_item_id', $mstItemIds)
            ->get();

        return $trxItems->map(fn (TrxItem $trxItem) => $this->modelToDto($trxItem))->all();
    }

    /**
     * TrxItemモデルをItemDtoに変換
     */
    private function modelToDto(TrxItem $trxItem): ItemDto
    {
        return new ItemDto(
            sysPlayerId: $trxItem->getSysPlayerId(),
            mstItemId: $trxItem->getMstItemId(),
            freeAmount: $trxItem->getFreeAmount(),
            paidAmount: $trxItem->getPaidAmount(),
        );
    }
}
