<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaPrize;
use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Support\CustomCollection;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;

/**
 * MstGachaPrizeRepository
 *
 * @extends _BaseMstRepository<MstGachaPrize>
 */
class MstGachaPrizeRepository extends _BaseMstRepository implements GachaPrizeRepositoryInterface
{
    protected string $modelClass = MstGachaPrize::class;

    /**
     * {@inheritDoc}
     *
     * @return CustomCollection<array-key, Model> インターフェースに合わせてModelで受ける
     */
    public function selectByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): CustomCollection
    {
        /** @var CustomCollection<array-key, Model> $contents インターフェースの型に合わせて広げる */
        $contents = $this->selectListByGachaIdAndRarity($mstGachaId, $rarity, $pickupOnly);

        return $contents;
    }

    /**
     * ガチャIDとレアリティで景品リストを取得
     *
     * @param  bool  $pickupOnly  ピックアップのみ取得
     * @return CustomCollection<int, MstGachaPrize>
     */
    public function selectListByGachaIdAndRarity(
        string $mstGachaId,
        int $rarity,
        bool $pickupOnly = false
    ): CustomCollection {
        $this->queryOrMemory();

        $query = $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('rarity', $rarity)
            ->where('is_active', true);

        if ($pickupOnly) {
            $query = $query->where('is_pickup', true);
        }

        return $query->values();
    }
}
