<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonusContent;
use Nexus\Core\Support\CustomCollection;
use NexusGacha\DataTransferObjects\BonusContent;
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
    public function selectByBonusId(string $bonusId): array
    {
        return $this->selectListByBonusId($bonusId)
            ->map(fn (MstGachaStepBonusContent $content) => $this->toBonusContent($content))
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function selectContentById(string $contentMstId): ?BonusContent
    {
        $content = parent::selectById($contentMstId);

        return $content instanceof MstGachaStepBonusContent ? $this->toBonusContent($content) : null;
    }

    private function toBonusContent(MstGachaStepBonusContent $content): BonusContent
    {
        return new BonusContent(
            id: $content->id,
            stepBonusId: $content->getMstGachaStepBonusId(),
            contentType: $content->getContentType(),
            contentMstId: $content->getContentMstId(),
            contentOption: $content->getContentOption(),
            amount: $content->getAmount(),
            weight: $content->getWeight(),
        );
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
