<?php

namespace App\Domain\Gacha\Services;

use NexusGacha\ValueObjects\GachaPrize;
use NexusGacha\Services\GachaPrizeService as PackageGachaPrizeService;

/**
 * GachaPrizeService
 *
 * パッケージ版のGachaPrizeServiceのラッパー
 */
class GachaPrizeService
{
    public function __construct(
        private readonly PackageGachaPrizeService $basePrizeService,
    ) {}

    /**
     * 景品リストを付与
     *
     * @param  array  $prizes  配列形式の景品リスト
     */
    public function grantPrizes(int $sysPlayerId, array $prizes): void
    {
        // 配列をDTOに変換
        $prizeDtos = array_map(function ($prize) {
            return new GachaPrize(
                contentType: $prize['content_type'],
                contentId: $prize['content_id'],
                amount: $prize['amount'],
                rarity: $prize['rarity'],
                isGuaranteed: $prize['is_guaranteed']
            );
        }, $prizes);

        $this->basePrizeService->grantPrizes($sysPlayerId, $prizeDtos);
    }
}
