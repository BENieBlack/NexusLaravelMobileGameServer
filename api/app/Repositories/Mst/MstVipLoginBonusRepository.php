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
            // 並びを固定する（配る順とカレンダーの 1 日の中の順を同じにする）。
            // sort_order 列が無いので登録順の id（連番）。mst_vip_login_bonus_content.id を string にする解消（MstRequiresStringIdTest の
            // PENDING_VIOLATIONS）をすると id の並びが辞書順に変わり配る順が変わるので、その時は sort_order 列を足して並びの鍵を移す
            // （設計 docs/design/login-bonus-calendar/api.md の U-S2-2）
            ->orderBy('id')
            ->get();

        return new CustomCollection($contents->toArray());
    }

    /**
     * {@inheritDoc}
     *
     * @return CustomCollection<array-key, array<string, mixed>> 中身はtoArray()した連想配列
     */
    public function selectContentsByBonusId(string $vipLoginBonusId): CustomCollection
    {
        // カレンダー用に全日の報酬を 1 クエリで取る（日ごとに引かない）。
        // day → id の順は、1 日分を引く selectContentsByBonusIdAndDay と同じ並び
        $contents = MstVipLoginBonusContent::where('mst_vip_login_bonus_id', $vipLoginBonusId)
            ->orderBy('day')
            ->orderBy('id')
            ->get();

        return new CustomCollection($contents->toArray());
    }
}
