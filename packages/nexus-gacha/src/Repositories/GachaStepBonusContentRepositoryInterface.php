<?php

namespace NexusGacha\Repositories;

use Illuminate\Database\Eloquent\Model;
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
     * @param  string  $bonusId
     * @return CustomCollection<array-key, Model>
     */
    public function selectByBonusId(string $bonusId): CustomCollection;

    /**
     * コンテンツIDでコンテンツを取得
     *
     * @param  string  $contentMstId
     * @return mixed|null
     */
    public function selectById(string $contentMstId): mixed;
}
