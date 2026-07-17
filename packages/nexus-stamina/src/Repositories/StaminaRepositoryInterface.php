<?php

namespace NexusStamina\Repositories;

use NexusStamina\Dto\StaminaDto;

/**
 * StaminaRepositoryInterface
 * 
 * スタミナデータへのアクセスを抽象化
 */
interface StaminaRepositoryInterface
{
    /**
     * プレイヤーのスタミナをタイプで取得
     * 
     * @param int $sysPlayerId
     * @param string $type
     * @return StaminaDto|null スタミナDTO、存在しない場合はnull
     */
    public function findByPlayerAndType(int $sysPlayerId, string $type): ?StaminaDto;

    /**
     * スタミナデータを保存
     * 
     * @param StaminaDto $stamina
     * @return void
     */
    public function save(StaminaDto $stamina): void;

    /**
     * 新規スタミナデータを作成
     * 
     * @param StaminaDto $stamina
     * @return StaminaDto 作成されたスタミナDTO
     */
    public function create(StaminaDto $stamina): StaminaDto;
}

