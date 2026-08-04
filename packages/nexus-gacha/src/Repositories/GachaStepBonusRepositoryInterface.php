<?php

namespace NexusGacha\Repositories;

use NexusPersistence\Support\CustomCollection;

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
     * @return CustomCollection
     */
    public function findByStepId(string $stepId): CustomCollection;
}
