<?php

namespace NexusGacha\Repositories;

use Illuminate\Database\Eloquent\Model;
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
     * @param  string  $mstGachaId
     * @return CustomCollection<array-key, Model>
     */
    public function selectByGachaId(string $mstGachaId): CustomCollection;
}
