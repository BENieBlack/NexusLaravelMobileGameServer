<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;

/**
 * EloquentLoginBonusRepository
 * 
 * Eloquent ORMを使用したログインボーナスマスタデータへのアクセス実装
 */
class EloquentLoginBonusRepository implements LoginBonusRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function getLoopDaysForActiveBonus(): ?int
    {
        return MstLoginBonus::where('is_active', true)
            ->where('day', 1)
            ->value('loop_days');
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveByDay(int $day): ?array
    {
        $bonus = MstLoginBonus::where('day', $day)
            ->where('is_active', true)
            ->first();

        return $bonus ? $bonus->toArray() : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findContentsByLoginBonusId(string $loginBonusId): array
    {
        return MstLoginBonusContent::where('mst_login_bonus_id', $loginBonusId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($content) => $content->toArray())
            ->all();
    }
}
