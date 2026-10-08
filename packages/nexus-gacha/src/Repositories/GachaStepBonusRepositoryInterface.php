<?php

namespace NexusGacha\Repositories;

use NexusGacha\DataTransferObjects\StepBonus;

/**
 * GachaStepBonusRepositoryInterface
 *
 * ガチャステップボーナスデータへのアクセスを抽象化
 */
interface GachaStepBonusRepositoryInterface
{
    /**
     * ステップIDでステップボーナスリストを取得（position順）
     *
     * @return list<StepBonus>
     */
    public function selectByStepId(string $stepId): array;
}
