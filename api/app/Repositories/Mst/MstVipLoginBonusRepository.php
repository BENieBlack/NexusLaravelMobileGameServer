<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstVipLoginBonus;
use App\Models\Mst\MstVipLoginBonusContent;
use Nexus\Core\Support\CustomCollection;

/**
 * VIPログインボーナスマスターのRepository
 *
 * @extends _BaseMstRepository<MstVipLoginBonus>
 */
class MstVipLoginBonusRepository extends _BaseMstRepository implements VipLoginBonusRepositoryInterface
{
    protected string $modelClass = MstVipLoginBonus::class;

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>|null
     */
    public function selectActiveByVipLevel(int $vipLevel): ?array
    {
        $model = MstVipLoginBonus::active()
            ->forVipLevel($vipLevel)
            // 同じレベルに有効な行が 2 件以上あるマスターの誤りがあっても、
            // 配布・カレンダー・再受け取りが同じ 1 件を選ぶように並びを固定する
            ->orderBy('id')
            ->first();

        return $model?->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * @return CustomCollection<array-key, array<string, mixed>> 中身はtoArray()した連想配列
     */
    public function selectContentsByBonusIdAndDay(string $vipLoginBonusId, int $day): CustomCollection
    {
        $contents = MstVipLoginBonusContent::where('mst_vip_login_bonus_id', $vipLoginBonusId)
            ->where('day', $day)
            // 並びは sort_desc 降順（大きい値が先。配る順 = クライアントの表示順）。同値は id 昇順で固定する。
            // id（連番）は mst_vip_login_bonus_content.id を string にする解消（MstRequiresStringIdTest の
            // PENDING_VIOLATIONS）で辞書順になっても、sort_desc が鍵なので配る順は変わらない
            // （設計 docs/design/login-bonus-calendar/api.md の U-S2-2）
            ->orderByDesc('sort_desc')
            ->orderBy('id')
            ->get();

        return new CustomCollection($contents->toArray());
    }
}
