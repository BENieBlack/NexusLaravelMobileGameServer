<?php

namespace NexusGacha\Repositories;

use NexusGacha\DataTransferObjects\RarityRate;

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
     * @return list<RarityRate>
     */
    public function selectByGachaId(string $mstGachaId): array;
}
