<?php

namespace NexusLogin\Repositories;

use NexusLogin\DataTransferObjects\LoginBonusHistory;

/**
 * LoginBonusHistoryRepositoryInterface
 *
 * ログインボーナスの受け取り状態（trx_login_bonus）と受取ログ（log_action_login_bonus_receive）への
 * アクセスを抽象化する。対象はログイン中のプレイヤーのシャード（実装側が解決する）
 */
interface LoginBonusHistoryRepositoryInterface
{
    /**
     * プレイヤーの通常ログインボーナスの最新の受け取り状態を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return LoginBonusHistory|null まだ受け取っていなければnull
     */
    public function selectLatestByPlayer(int $sysPlayerId): ?LoginBonusHistory;

    /**
     * 指定日時以降に通常ログインボーナスを受け取った件数をカウント
     *
     * trx_login_bonus はタイプごとに1行なので 0 か 1 になる
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $sinceDate  開始日時（Y-m-d H:i:s）
     */
    public function countUniqueDaysSince(int $sysPlayerId, string $sinceDate): int;

    /**
     * 受け取った報酬1件分を記録する
     *
     * 最新の受け取り状態（タイプごとに1行）を書き換え、受取ログを1行追加する
     *
     * @param  string  $type  ログインボーナスのタイプ（daily / comeback）
     * @param  string  $receivedDate  受け取り日時（Y-m-d H:i:s形式）
     */
    public function insertReceive(
        int $sysPlayerId,
        string $type,
        string $mstLoginBonusId,
        int $day,
        ?int $absentDays,
        string $receivedDate,
        string $rewardType,
        string $rewardMstId,
        int $rewardAmount,
        bool $isPaid,
    ): void;

    /**
     * プレイヤーの通常ログインボーナスの最新の受け取り状態を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function selectLatestByPlayerId(int $sysPlayerId): ?LoginBonusHistory;

    /**
     * プレイヤーのカムバックボーナスの受け取り状態を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function selectFirstComebackByPlayerId(int $sysPlayerId): ?LoginBonusHistory;

    /**
     * プレイヤーのカムバックボーナスの受け取り状態が、指定したボーナスIDと受取日のものなら返す
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $bonusId  ログインボーナスID
     * @param  string  $receivedDate  受取日（Y-m-d H:i:s形式）
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $bonusId,
        string $receivedDate,
    ): ?LoginBonusHistory;
}
