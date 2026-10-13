<?php

namespace NexusGacha\Repositories;

/**
 * GachaStepRepositoryInterface
 *
 * ガチャステップデータへのアクセスを抽象化
 */
interface GachaStepRepositoryInterface
{
    /**
     * ガチャIDとステップ番号でステップIDを取得
     *
     * @return string|null ステップが無ければnull
     */
    public function selectStepIdByGachaIdAndNumber(string $mstGachaId, int $stepNumber): ?string;
}
