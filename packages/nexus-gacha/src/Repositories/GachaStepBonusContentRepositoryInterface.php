<?php

namespace NexusGacha\Repositories;

use Nexus\Core\Support\CustomCollection;

/**
 * GachaStepBonusContentRepositoryInterface
 * 
 * ガチャステップボーナスコンテンツデータへのアクセスを抽象化
 */
interface GachaStepBonusContentRepositoryInterface
{
    /**
     * ボーナスIDでコンテンツリストを取得
     * 
     * @param string $bonusId
     * @return CustomCollection<array-key, \Illuminate\Database\Eloquent\Model>
     */
    public function selectByBonusId(string $bonusId): CustomCollection;

    /**
     * コンテンツIDでコンテンツを取得
     * 
     * @param string $contentId
     * @return mixed|null
     */
    public function selectById(string $contentId): mixed;
}
