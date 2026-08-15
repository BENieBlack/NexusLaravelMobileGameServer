<?php

namespace NexusLogin\Repositories;

/**
 * LoginBonusHistoryRepositoryInterface
 * 
 * ログインボーナス履歴データへのアクセスを抽象化
 */
interface LoginBonusHistoryRepositoryInterface
{
    /**
     * プレイヤーの最新のログインボーナス履歴を取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $connectionName DB接続名
     * @return array|null 履歴データの連想配列、存在しない場合はnull
     */
    public function selectLatestByPlayer(int $sysPlayerId, string $connectionName): ?array;

    /**
     * 指定日時以降のユニークな受取日数をカウント
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $sinceDate 開始日時（Y-m-d H:i:s）
     * @param string $connectionName DB接続名
     * @return int ユニークな受取日数
     */
    public function countUniqueDaysSince(int $sysPlayerId, string $sinceDate, string $connectionName): int;

    /**
     * ログインボーナス履歴を記録
     * 
     * @param array $data 履歴データ
     * @param string $connectionName DB接続名
     * @return void
     */
    public function insert(array $data, string $connectionName): void;

    /**
     * プレイヤーの最新履歴を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $connectionName シャーディングされたDB接続名
     * @return array|null 履歴データ
     */
    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array;

    /**
     * プレイヤーの最初のカムバックボーナス履歴を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $connectionName シャーディングされたDB接続名
     * @return array|null 履歴データ
     */
    public function selectFirstComebackByPlayerId(int $sysPlayerId, string $connectionName): ?array;

    /**
     * プレイヤー・ボーナス・受取日で履歴を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $bonusId ログインボーナスID
     * @param string $receivedDate 受取日
     * @param string $connectionName シャーディングされたDB接続名
     * @return array|null 履歴データ
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $bonusId,
        string $receivedDate,
        string $connectionName
    ): ?array;
}
