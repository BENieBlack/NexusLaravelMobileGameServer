<?php

namespace App\Repositories\Mst;

use NexusPersistence\Support\CustomCollection;

interface VipLoginBonusRepositoryInterface
{
    /**
     * VIPレベルに対応する有効なVIPログインボーナスを取得
     *
     * @param  int  $vipLevel  VIPレベル
     */
    public function findActiveByVipLevel(int $vipLevel): ?array;

    /**
     * VIPログインボーナスIDと日数から報酬内容を取得
     *
     * @param  string  $vipLoginBonusId  VIPログインボーナスID
     * @param  int  $day  日数
     */
    public function findContentsByBonusIdAndDay(string $vipLoginBonusId, int $day): CustomCollection;
}
