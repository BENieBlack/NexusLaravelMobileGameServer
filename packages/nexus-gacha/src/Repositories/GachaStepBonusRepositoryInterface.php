<?php

namespace NexusGacha\Repositories;

use Nexus\Core\Support\CustomCollection;

/**
 * GachaStepBonusRepositoryInterface
 * 
 * ガチャステップボーナスデータへのアクセスを抽象化
 */
interface GachaStepBonusRepositoryInterface
{
    /**
     * ステップIDでステップボーナスリストを取得
     * 
     * @param string $stepId
     * @return CustomCollection<array-key, \Illuminate\Database\Eloquent\Model>
     */
    public function selectByStepId(string $stepId): CustomCollection;
}
