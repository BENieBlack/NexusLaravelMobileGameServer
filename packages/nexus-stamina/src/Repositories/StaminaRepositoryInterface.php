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
     * @return StaminaDto|null スタミナDTO、存在しない場合はnull
     */
    public function selectByPlayerAndType(int $sysPlayerId, string $type): ?StaminaDto;

    /**
     * スタミナデータを保存
     */
    public function save(StaminaDto $staminaDto): void;

    /**
     * 新規スタミナデータを作成
     *
     * @return StaminaDto 作成されたスタミナDTO
     */
    public function insert(StaminaDto $staminaDto): StaminaDto;
}
