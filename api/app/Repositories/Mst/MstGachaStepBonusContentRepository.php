<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonusContent;
use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Support\CustomCollection;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;

/**
 * MstGachaStepBonusContentRepository
 *
 * @extends _BaseMstRepository<MstGachaStepBonusContent>
 */
class MstGachaStepBonusContentRepository extends _BaseMstRepository implements GachaStepBonusContentRepositoryInterface
{
    protected string $modelClass = MstGachaStepBonusContent::class;

    /**
     * {@inheritDoc}
     *
     * @return CustomCollection<array-key, Model> インターフェースに合わせてModelで受ける
     */
    public function selectByBonusId(string $bonusId): CustomCollection
    {
        /** @var CustomCollection<array-key, Model> $contents インターフェースの型に合わせて広げる */
        $contents = $this->selectListByBonusId($bonusId);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function selectById($contentMstId): mixed
    {
        return parent::selectById($contentMstId);
    }

    /**
     * ステップボーナスIDでコンテンツリストを取得
     *
     * @return CustomCollection<int, MstGachaStepBonusContent>
     */
    public function selectListByBonusId(string $mstGachaStepBonusId): CustomCollection
    {
        $this->queryOrMemory();

        return $this->models
            ->where('mst_gacha_step_bonus_id', $mstGachaStepBonusId)
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();
    }
}
