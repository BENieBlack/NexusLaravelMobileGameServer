<?php

namespace App\Repositories\Trx;

use Illuminate\Support\Facades\DB;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;

/**
 * TrxLoginBonusHistoryRepository
 *
 * Query Builderを使用したログインボーナス履歴データへのアクセス実装
 */
class TrxLoginBonusHistoryRepository implements LoginBonusHistoryRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function selectLatestByPlayer(int $sysPlayerId, string $connectionName): ?array
    {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->orderBy('received_date', 'desc')
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * findLatestByPlayerのエイリアス
     */
    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array
    {
        return $this->selectLatestByPlayer($sysPlayerId, $connectionName);
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

    /**
     * プレイヤーの最初のカムバックボーナス受取履歴を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $connectionName  シャーディングされたDB接続名
     * @return array|null 最初のカムバック履歴（なければnull）
     */
    public function selectFirstComebackByPlayerId(int $sysPlayerId, string $connectionName): ?array
    {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_login_bonus_id', 'like', 'comeback%')
            ->orderBy('received_date', 'asc')
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * 指定日に特定のボーナスを受け取ったかチェック
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $bonusId  ログインボーナスID
     * @param  string  $receivedDate  受け取り日時（Y-m-d H:i:s形式）
     * @param  string  $connectionName  シャーディングされたDB接続名
     * @return array|null 履歴（なければnull）
     */
    public function selectByPlayerAndBonusAndDate(int $sysPlayerId, string $bonusId, string $receivedDate, string $connectionName): ?array
    {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_login_bonus_id', $bonusId)
            ->where('received_date', $receivedDate)
            ->first();

        return $result ? (array) $result : null;
    }
}
