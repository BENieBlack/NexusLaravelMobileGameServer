<?php

namespace NexusLevel\DataTransferObjects;

/**
 * PlayerLevelMaster
 *
 * プレイヤーレベルのマスター（mst_player_level の1行）を表すDTO
 */
class PlayerLevelMaster
{
    public function __construct(
        private readonly int $level,
        private readonly int $requiredExp,
        private readonly int $maxStamina,
    ) {}

    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * このレベルに必要な累積経験値
     */
    public function getRequiredExp(): int
    {
        return $this->requiredExp;
    }

    public function getMaxStamina(): int
    {
        return $this->maxStamina;
    }
}
