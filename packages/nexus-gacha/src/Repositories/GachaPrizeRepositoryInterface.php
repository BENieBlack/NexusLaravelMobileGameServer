<?php

namespace NexusGacha\Repositories;

use NexusPersistence\Support\CustomCollection;

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
     * @param string $mstGachaId
     * @param int $rarity
     * @param bool $pickupOnly
     * @return CustomCollection
     */
    public function findByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): CustomCollection;
}
