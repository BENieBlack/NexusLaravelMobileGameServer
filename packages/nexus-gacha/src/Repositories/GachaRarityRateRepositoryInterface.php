<?php

namespace NexusGacha\Repositories;

use NexusPersistence\Support\CustomCollection;

/**
 * GachaRarityRateRepositoryInterface
 * 
 * ガチャレアリティ排出率データへのアクセスを抽象化
 */
interface GachaRarityRateRepositoryInterface
{
    /**
     * ガチャIDでレアリティ確率リストを取得
     * 
     * @param string $mstGachaId
     * @return CustomCollection
     */
    public function findByGachaId(string $mstGachaId): CustomCollection;
}
