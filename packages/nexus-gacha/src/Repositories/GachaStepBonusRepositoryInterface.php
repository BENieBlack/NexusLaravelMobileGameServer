<?php

namespace NexusGacha\Repositories;

use Illuminate\Database\Eloquent\Model;
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
     * @param  string  $stepId
     * @return CustomCollection<array-key, Model>
     */
    public function selectByStepId(string $stepId): CustomCollection;
}
