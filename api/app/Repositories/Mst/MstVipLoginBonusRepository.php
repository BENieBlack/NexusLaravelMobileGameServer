<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstVipLoginBonus;
use App\Models\Mst\MstVipLoginBonusContent;
use Nexus\Core\Support\CustomCollection;

class MstVipLoginBonusRepository extends _BaseMstRepository implements VipLoginBonusRepositoryInterface
{
    protected string $modelClass = MstVipLoginBonus::class;

    /**
     * {@inheritDoc}
     */
    public function findActiveByVipLevel(int $vipLevel): ?array
    {
        $model = MstVipLoginBonus::active()
            ->forVipLevel($vipLevel)
            ->first();

        return $model?->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findContentsByBonusIdAndDay(string $vipLoginBonusId, int $day): CustomCollection
    {
        $contents = MstVipLoginBonusContent::where('mst_vip_login_bonus_id', $vipLoginBonusId)
            ->where('day', $day)
            ->get();

        return new CustomCollection($contents->toArray());
    }
}
