<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;

/**
 * LoginBonusRepositoryAdapter
 *
 * Eloquent ORMを使用したログインボーナスマスタデータへのアクセス実装
 */
class LoginBonusRepositoryAdapter implements LoginBonusRepositoryInterface
{
    public function __construct(
        private readonly LoginBonusHistoryRepositoryInterface $historyRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectLoopDaysForActiveBonus(): ?int
    {
        return MstLoginBonus::dailyType()
            ->active()
            ->where('day', 1)
            ->value('loop_days');
    }

    /**
     * {@inheritDoc}
     */
    public function selectActiveByDay(int $day): ?array
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
    public function selectActiveDailyBonus(): ?array
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
    public function selectContentsByLoginBonusId(string $loginBonusId): array
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
    public function selectEligibleComebackBonus(int $absentDays): ?array
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
    public function selectContentsByLoginBonusIdAndDay(string $loginBonusId, int $day): array
    {
        // カムバックボーナスの場合、dayは無視して全コンテンツを返す
        return MstLoginBonusContent::where('mst_login_bonus_id', $loginBonusId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($content) => $content->toArray())
            ->all();
    }
}
