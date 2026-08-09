<?php

namespace NexusResource\Contracts;

use NexusResource\DTOs\ItemDto;

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
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @return ItemDto|null
     */
    public function findItem(int $sysPlayerId, string $mstItemId): ?ItemDto;

    /**
     * アイテムを保存（新規作成 or 更新）
     *
     * @param ItemDto $itemDto
     * @return void
     */
    public function saveItem(ItemDto $itemDto): void;

    /**
     * 複数アイテムを一括取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param array<string> $mstItemIds アイテムIDリスト
     * @return array<ItemDto>
     */
    public function findItemsByIds(int $sysPlayerId, array $mstItemIds): array;
}
