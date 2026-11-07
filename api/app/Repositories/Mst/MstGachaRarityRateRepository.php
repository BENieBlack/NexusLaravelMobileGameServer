<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaRarityRate;
use Nexus\Core\Support\CustomCollection;
use NexusGacha\DataTransferObjects\RarityRate;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;

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
    public function selectByGachaId(string $mstGachaId): array
    {
        return array_values($this->selectListByGachaId($mstGachaId)
            ->map(fn (MstGachaRarityRate $rate) => new RarityRate(
                rarity: $rate->rarity,
                rate: $rate->rate,
            ))
            ->all());
    }

    /**
     * ガチャIDでレアリティ排出率リストを取得
     *
     * @return CustomCollection<int, MstGachaRarityRate>
     */
    public function selectListByGachaId(string $mstGachaId): CustomCollection
    {
        $models = $this->queryOrMemory();

        return $models
            ->where('mst_gacha_id', $mstGachaId)
            ->sortBy('rarity')
            ->values();
    }
}
