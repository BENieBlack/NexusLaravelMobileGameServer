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
     * ガチャIDとステップ番号でステップ情報を取得
     * 
     * @param string $mstGachaId
     * @param int $stepNumber
     * @return mixed|null
     */
    public function findByGachaIdAndNumber(string $mstGachaId, int $stepNumber): mixed;
}
