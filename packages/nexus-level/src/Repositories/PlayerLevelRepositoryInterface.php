<?php

namespace NexusLevel\Repositories;

use NexusLevel\DataTransferObjects\PlayerLevelMaster;

/**
 * PlayerLevelRepositoryInterface
 *
 * プレイヤーレベルマスターデータへのアクセスを抽象化
 */
interface PlayerLevelRepositoryInterface
{
    /**
     * レベルからレベルデータを取得
     */
    public function selectByLevel(int $level): ?PlayerLevelMaster;

    /**
     * 累積経験値からレベルを計算
     */
    public function calculateLevelFromExp(int $exp): int;

    /**
     * 最大レベルを取得
     */
    public function selectMaxLevel(): int;

    /**
     * レベルに対応する最大スタミナを取得
     */
    public function findMaxStaminaForLevel(int $level): ?int;
}
