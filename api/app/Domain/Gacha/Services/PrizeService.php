<?php

namespace App\Domain\Gacha\Services;

use NexusGacha\Services\GachaPrizeService as BaseGachaPrizeService;
use NexusGacha\Dto\GachaPrizeDto;

/**
 * PrizeService
 *
 * パッケージ版のGachaPrizeServiceのラッパー
 */
class PrizeService
{
    public function __construct(
        private readonly BaseGachaPrizeService $basePrizeService,
    ) {
    }

    /**
     * 景品リストを付与
     *
     * @param int $sysPlayerId
     * @param array $prizes 配列形式の景品リスト
     * @return void
     */
    public function grantPrizes(int $sysPlayerId, array $prizes): void
    {
        // 配列をDTOに変換
        $prizeDtos = array_map(function ($prize) {
            return new GachaPrizeDto(
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

