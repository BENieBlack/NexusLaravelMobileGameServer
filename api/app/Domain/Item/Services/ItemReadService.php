<?php

namespace App\Domain\Item\Services;

use NexusResource\Services\ItemReadService as PackageItemReadService;

/**
 * ItemReadService (Domain層ラッパー)
 *
 * パッケージ層のItemReadServiceをラップ
 *
 * Design Pattern: Wrapper Pattern
 * - Package層: DTOベースの読み取りロジック
 * - Domain層: パッケージ層への委譲のみ
 *
 * Responsibilities:
 * - パッケージ層Serviceへの委譲
 *
 * Note: ビジネスロジックはパッケージ層（NexusResource\Services\ItemReadService）に存在
 */
class ItemReadService
{
    public function __construct(
        private readonly PackageItemReadService $packageItemReadService,
    ) {}

    /**
     * アイテムの所持数を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function findItemAmount(int $sysPlayerId, string $mstItemId): int
    {
        // パッケージ層に委譲
        return $this->packageItemReadService->findItemAmount($sysPlayerId, $mstItemId);
    }
}
