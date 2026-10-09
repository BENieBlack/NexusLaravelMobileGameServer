<?php

namespace App\Domain\Item\Contracts;

use App\Models\Trx\TrxItem;

/**
 * ItemInventoryInterface
 *
 * Itemモジュールの公開API。プレイヤーの所持アイテムを増減する。
 * 他のモジュール（装備・ユニットの強化素材、ガチャのコスト、課金パック）はこれだけを使う。
 */
interface ItemInventoryInterface
{
    /**
     * アイテムを付与する
     *
     * @param  string|null  $expireAt  有効期限（無期限ならnull）
     */
    public function addItem(
        int $sysPlayerId,
        string $mstItemId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?string $expireAt = null,
    ): void;

    /**
     * アイテムを消費する
     *
     * @return TrxItem 消費後のアイテム
     *
     * @throws \Exception 所持数が足りない場合
     */
    public function consumeItem(int $sysPlayerId, string $mstItemId, int $amount): TrxItem;

    /**
     * アイテムの所持数を返す
     */
    public function findItemAmount(int $sysPlayerId, string $mstItemId): int;
}
