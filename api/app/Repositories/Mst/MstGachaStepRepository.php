<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStep;
use Illuminate\Support\Collection;

/**
 * MstGachaStepRepository
 * 
 * @extends _BaseMstRepository<MstGachaStep>
 */
class MstGachaStepRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaStep::class;

    /**
     * ガチャIDとステップ番号でステップ情報を取得
     *
     * @param string $mstGachaId
     * @param int $stepNumber
     * @return MstGachaStep|null
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
     * @param string $mstGachaId
     * @return Collection<int, MstGachaStep>
     */
    public function selectListByGachaId(string $mstGachaId): Collection
    {
        $this->queryOrMemory();
        
        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_active', true)
            ->sortBy('step_number')
            ->values();
    }
}
