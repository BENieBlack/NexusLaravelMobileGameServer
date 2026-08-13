<?php

namespace NexusResource\Services;

use NexusResource\Contracts\ItemRepositoryInterface;

/**
 * ItemReadService
 *
 * アイテム残高の読み取り専用サービス（パッケージ層）
 * 
 * Responsibilities:
 * - アイテム残高の取得（getItemAmount）
 * - 複数アイテム残高の一括取得
 * 
 * Characteristics:
 * - DTOベースの読み取り専用ロジック
 * - Repository Interfaceに依存（Model非依存）
 * - キャッシュ可能な設計
 */
class ItemReadService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
    ) {
    }

    /**
     * アイテムの所持数を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function getItemAmount(int $sysPlayerId, string $mstItemId): int
    {
        $itemDto = $this->itemRepository->selectItem($sysPlayerId, $mstItemId);

        return $itemDto ? $itemDto->getTotalAmount() : 0;
    }

    /**
     * 複数アイテムの所持数を一括取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param array<string> $mstItemIds アイテムIDリスト
     * @return array<string, int> アイテムID => 所持数のマップ
     */
    public function getItemAmounts(int $sysPlayerId, array $mstItemIds): array
    {
        $itemDtos = $this->itemRepository->selectItemsByIds($sysPlayerId, $mstItemIds);

        $amounts = [];
        foreach ($itemDtos as $itemDto) {
            $amounts[$itemDto->getMstItemId()] = $itemDto->getTotalAmount();
        }

        // 存在しないアイテムは0を設定
        foreach ($mstItemIds as $mstItemId) {
            if (!isset($amounts[$mstItemId])) {
                $amounts[$mstItemId] = 0;
            }
        }

        return $amounts;
    }
}
