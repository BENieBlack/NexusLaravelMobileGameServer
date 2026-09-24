<?php

namespace NexusResource\Contracts;

use NexusResource\DataTransferObjects\Item;

/**
 * ItemRepositoryInterface
 *
 * アイテムの永続化操作を抽象化するインターフェース
 * 実装はDomain層で行い、パッケージ層はこのインターフェースに依存する
 */
interface ItemRepositoryInterface
{
    /**
     * アイテムを検索
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @return Item|null
     */
    public function selectItem(int $sysPlayerId, string $mstItemId): ?Item;

    /**
     * アイテムを保存（新規作成 or 更新）
     *
     * @param  Item  $item
     * @return void
     */
    public function persistItem(Item $item): void;

    /**
     * 複数アイテムを一括取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  array<string>  $mstItemIds  アイテムIDリスト
     * @return array<Item>
     */
    public function selectItemsByIds(int $sysPlayerId, array $mstItemIds): array;
}
