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
     * @param int $amount 加算する数量
     * @return void
     */
    public function addItem(int $sysPlayerId, string $mstItemId, int $amount): void
    {

        // 既存のアイテムを取得
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        if ($trxItem) {
            // 既存アイテムがある場合は加算
            $trxItem->setAmount($trxItem->getAmount() + $amount);
        } else {
            // 新規アイテムを作成
            $trxItem = new TrxItem([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'amount' => $amount,
            ]);
            $trxItem->exists = false; // INSERT として認識
        }

        // setModelで内部キューに溜め込む
        $this->trxItemRepository->setModel($trxItem);
    }

    /**
     * アイテムを消費（減算）
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

        if ($trxItem->getAmount() < $amount) {
            throw new \Exception("Insufficient item amount. Required: {$amount}, Available: {$trxItem->getAmount()}");
        }

        // アイテムを減算
        $trxItem->setAmount($trxItem->getAmount() - $amount);

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
     * @return int 所持数（存在しない場合は0）
     */
    public function getItemAmount(int $sysPlayerId, string $mstItemId): int
    {

        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $trxItem ? $trxItem->getAmount() : 0;
    }
}
