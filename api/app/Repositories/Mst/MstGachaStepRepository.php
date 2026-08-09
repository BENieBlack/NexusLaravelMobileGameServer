<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStep;
use NexusGacha\Repositories\GachaStepRepositoryInterface;
use Nexus\Core\Support\CustomCollection;

/**
 * MstGachaStepRepository
 *
 * @extends _BaseMstRepository<MstGachaStep>
 */
class MstGachaStepRepository extends _BaseMstRepository implements GachaStepRepositoryInterface
{
    protected string $modelClass = MstGachaStep::class;

    /**
     * {@inheritDoc}
     */
    public function findByGachaIdAndNumber(string $mstGachaId, int $stepNumber): mixed
    {
        return $this->selectByGachaIdAndStepNumber($mstGachaId, $stepNumber);
    }

    /**
     * ガチャIDとステップ番号でステップ情報を取得
     */
    public function selectByGachaIdAndStepNumber(string $mstGachaId, int $stepNumber): ?MstGachaStep
    {
        $this->queryOrMemory();

        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('step_number', $stepNumber)
            ->where('is_active', true)
            ->first();
    }

    /**
     * ガチャIDでステップリストを取得
     *
     * @return CustomCollection<int, MstGachaStep>
     */
    public function selectListByGachaId(string $mstGachaId): CustomCollection
    {
        $this->queryOrMemory();

        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_active', true)
            ->sortBy('step_number')
            ->values();
    }
}
