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
     * @param int $level
     * @return array{level: int, required_exp: int, max_stamina: int}|null
     */
    public function findByLevel(int $level): ?array;

    /**
     * 累積経験値からレベルを計算
     * 
     * @param int $exp
     * @return int
     */
    public function calculateLevelFromExp(int $exp): int;

    /**
     * 最大レベルを取得
     * 
     * @return int
     */
    public function getMaxLevel(): int;

    /**
     * レベルに対応する最大スタミナを取得
     * 
     * @param int $level
     * @return int|null
     */
    public function getMaxStaminaForLevel(int $level): ?int;
}
