<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaRarityRate;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use Nexus\Core\Support\CustomCollection;

/**
 * MstGachaRarityRateRepository
 *
 * @extends _BaseMstRepository<MstGachaRarityRate>
 */
class MstGachaRarityRateRepository extends _BaseMstRepository implements GachaRarityRateRepositoryInterface
{
    protected string $modelClass = MstGachaRarityRate::class;

    /**
     * {@inheritDoc}
     */
    public function findByGachaId(string $mstGachaId): CustomCollection
    {
        return $this->selectListByGachaId($mstGachaId);
    }

    /**
     * ガチャIDでレアリティ排出率リストを取得
     *
     * @return CustomCollection<int, MstGachaRarityRate>
     */
    public function selectListByGachaId(string $mstGachaId): CustomCollection
    {
        $this->queryOrMemory();

        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->sortBy('rarity')
            ->values();
    }
}
