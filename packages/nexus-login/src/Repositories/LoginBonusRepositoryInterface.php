<?php

namespace NexusLogin\Repositories;

/**
 * LoginBonusRepositoryInterface
 * 
 * ログインボーナスマスタデータへのアクセスを抽象化
 */
interface LoginBonusRepositoryInterface
{
    /**
     * アクティブなログインボーナス設定のloop_daysを取得
     * 
     * @return int|null ループ日数、設定がない場合はnull
     */
    public function getLoopDaysForActiveBonus(): ?int;

    /**
     * 指定された日のアクティブなログインボーナスを取得
     * 
     * @param int $day 日数（1〜loop_days）
     * @return array|null ログインボーナスデータの連想配列、存在しない場合はnull
     */
    public function selectActiveByDay(int $day): ?array;

    /**
     * ログインボーナスIDに紐づく報酬内容を取得
     * 
     * @param string $loginBonusId ログインボーナスID
     * @return array 報酬内容の配列（sort_order順）
     */
    public function selectContentsByLoginBonusId(string $loginBonusId): array;

    /**
     * 指定された休眠日数に該当するカムバックボーナスを取得
     * 
     * @param int $absentDays 休眠日数
     * @return array|null カムバックボーナスデータ（優先度順で最も高いもの）
     */
    public function selectEligibleComebackBonus(int $absentDays): ?array;

    /**
     * プレイヤーが特定のカムバックボーナスを最近受け取ったかチェック
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $comebackBonusId カムバックボーナスID
     * @param int $validDays 有効期間（日数）
     * @param string $connectionName シャーディングされたDB接続名
     * @return bool 受け取り済みならtrue
     */
    public function hasReceivedComebackBonusRecently(
        int $sysPlayerId,
        string $comebackBonusId,
        int $validDays,
        string $connectionName
    ): bool;
}
