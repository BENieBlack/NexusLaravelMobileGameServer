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
    public function findActiveByDay(int $day): ?array;

    /**
     * ログインボーナスIDに紐づく報酬内容を取得
     * 
     * @param string $loginBonusId ログインボーナスID
     * @return array 報酬内容の配列（sort_order順）
     */
    public function findContentsByLoginBonusId(string $loginBonusId): array;
}
