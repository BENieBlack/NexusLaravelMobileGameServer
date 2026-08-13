<?php

namespace NexusGacha\Repositories;

use Nexus\Core\Support\CustomCollection;

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
    public function selectByGachaId(string $mstGachaId): CustomCollection;
}
