<?php

namespace NexusGacha\Repositories;

use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Support\CustomCollection;

/**
 * GachaPrizeRepositoryInterface
 *
 * ガチャ景品データへのアクセスを抽象化
 */
interface GachaPrizeRepositoryInterface
{
    /**
     * ガチャIDとレアリティで景品リストを取得
     *
     * @param  string  $mstGachaId
     * @param  int  $rarity
     * @param  bool  $pickupOnly
     * @return CustomCollection<array-key, Model>
     */
    public function selectByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): CustomCollection;
}
