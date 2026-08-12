<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonusContent;
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
     */
    public function findByBonusId(string $bonusId): CustomCollection
    {
        return $this->selectListByBonusId($bonusId);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(string $contentId): mixed
    {
        return $this->selectById($contentId);
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
