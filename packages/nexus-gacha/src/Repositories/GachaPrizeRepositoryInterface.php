<?php

namespace NexusGacha\Repositories;

use NexusGacha\DataTransferObjects\PrizeCandidate;

/**
 * GachaPrizeRepositoryInterface
 *
 * ガチャ景品データへのアクセスを抽象化
 */
interface GachaPrizeRepositoryInterface
{
    /**
     * ガチャIDとレアリティで景品リストを取得
     *
     * @return list<PrizeCandidate>
     */
    public function selectByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): array;
}
