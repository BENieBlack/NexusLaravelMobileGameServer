<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaRarityRate;
use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Support\CustomCollection;
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
     *
     * @return CustomCollection<array-key, Model> インターフェースに合わせてModelで受ける
     */
    public function selectByGachaId(string $mstGachaId): CustomCollection
    {
        /** @var CustomCollection<array-key, Model> $contents インターフェースの型に合わせて広げる */
        $contents = $this->selectListByGachaId($mstGachaId);

        return $contents;
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
