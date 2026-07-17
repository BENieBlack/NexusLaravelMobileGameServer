<?php

namespace App\Repositories\Trx;

use Illuminate\Support\Facades\DB;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;

/**
 * EloquentLoginBonusHistoryRepository
 * 
 * Eloquent/Query Builderを使用したログインボーナス履歴データへのアクセス実装
 */
class EloquentLoginBonusHistoryRepository implements LoginBonusHistoryRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findLatestByPlayer(int $sysPlayerId, string $connectionName): ?array
    {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->orderBy('received_date', 'desc')
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function countUniqueDaysSince(int $sysPlayerId, string $sinceDate, string $connectionName): int
    {
        return DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->where('received_date', '>=', $sinceDate)
            ->distinct()
            ->count('received_date');
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data, string $connectionName): void
    {
        DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->insert($data);
    }
}
