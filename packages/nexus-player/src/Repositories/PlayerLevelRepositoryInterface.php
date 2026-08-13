<?php

namespace NexusPlayer\Repositories;

/**
 * PlayerLevelRepositoryInterface
 *
 * プレイヤーレベルマスターデータへのアクセスを抽象化
 */
interface PlayerLevelRepositoryInterface
{
    /**
     * レベルからレベルデータを取得
     *
     * @return array{level: int, required_exp: int, max_stamina: int}|null
     */
    public function selectByLevel(int $level): ?array;

    /**
     * 累積経験値からレベルを計算
     */
    public function calculateLevelFromExp(int $exp): int;

    /**
     * 最大レベルを取得
     */
    public function getMaxLevel(): int;

    /**
     * レベルに対応する最大スタミナを取得
     */
    public function getMaxStaminaForLevel(int $level): ?int;
}
