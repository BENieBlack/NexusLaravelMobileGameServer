<?php

namespace NexusStamina\Repositories;

use NexusStamina\DataTransferObjects\Stamina;

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
     * @return Stamina|null スタミナDTO、存在しない場合はnull
     */
    public function selectByPlayerAndType(int $sysPlayerId, string $type): ?Stamina;

    /**
     * スタミナデータを保存
     */
    public function persist(Stamina $stamina): void;

    /**
     * 新規スタミナデータを作成
     *
     * @return Stamina 作成されたスタミナDTO
     */
    public function insert(Stamina $stamina): Stamina;
}
