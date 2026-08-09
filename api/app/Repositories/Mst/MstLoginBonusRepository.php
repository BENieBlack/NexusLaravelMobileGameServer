<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusUtilities\ClockUtility;

/**
 * MstLoginBonusRepository
 *
 * Eloquent ORMを使用したログインボーナスマスタデータへのアクセス実装
 */
class MstLoginBonusRepository implements LoginBonusRepositoryInterface
{
    public function __construct(
        private readonly LoginBonusHistoryRepositoryInterface $historyRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getLoopDaysForActiveBonus(): ?int
    {
        return MstLoginBonus::dailyType()
            ->active()
            ->where('day', 1)
            ->value('loop_days');
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveByDay(int $day): ?array
    {
        $bonus = MstLoginBonus::dailyType()
            ->active()
            ->where('day', $day)
            ->first();

        return $bonus ? $bonus->toArray() : null;
    }

    /**
     * 有効な通常ログインボーナスを取得
     *
     * @return array|null ログインボーナス設定
     */
    public function findActiveDailyBonus(): ?array
    {
        $bonus = MstLoginBonus::dailyType()
            ->active()
            ->where('day', 1)
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
            ->map(fn ($content) => $content->toArray())
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findEligibleComebackBonus(int $absentDays): ?array
    {
        $now = ClockUtility::now();

        $bonus = MstLoginBonus::comebackType()
            ->active()
            ->where('required_absent_days', '<=', $absentDays)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('required_absent_days')
            ->first();

        return $bonus ? $bonus->toArray() : null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasReceivedComebackBonusRecently(
        int $sysPlayerId,
        string $comebackBonusId,
        int $validDays,
        string $connectionName
    ): bool {
        $validFrom = ClockUtility::now()->subDays($validDays)->format('Y-m-d H:i:s');

        $history = \DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_login_bonus_id', $comebackBonusId)
            ->where('received_date', '>=', $validFrom)
            ->exists();

        return $history;
    }

    /**
     * ログインボーナスIDと日数でコンテンツを取得
     *
     * @param  string  $loginBonusId  ログインボーナスID
     * @param  int  $day  日数
     * @return array コンテンツの配列
     */
    public function findContentsByLoginBonusIdAndDay(string $loginBonusId, int $day): array
    {
        // カムバックボーナスの場合、dayは無視して全コンテンツを返す
        return MstLoginBonusContent::where('mst_login_bonus_id', $loginBonusId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($content) => $content->toArray())
            ->all();
    }
}
