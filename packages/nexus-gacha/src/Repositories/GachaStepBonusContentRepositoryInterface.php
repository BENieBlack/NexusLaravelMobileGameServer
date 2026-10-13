<?php

namespace NexusGacha\Repositories;

use NexusGacha\DataTransferObjects\BonusContent;

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
     * @return list<BonusContent>
     */
    public function selectByBonusId(string $bonusId): array;

    /**
     * コンテンツIDでコンテンツを取得
     */
    public function selectContentById(string $contentMstId): ?BonusContent;
}
