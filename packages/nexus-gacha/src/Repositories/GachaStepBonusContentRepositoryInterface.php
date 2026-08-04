<?php

namespace NexusGacha\Repositories;

use NexusPersistence\Support\CustomCollection;

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
     * @return CustomCollection
     */
    public function findByBonusId(string $bonusId): CustomCollection;

    /**
     * コンテンツIDでコンテンツを取得
     * 
     * @param string $contentId
     * @return mixed|null
     */
    public function findById(string $contentId): mixed;
}
