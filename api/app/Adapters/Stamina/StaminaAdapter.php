<?php

namespace App\Adapters\Stamina;

use App\Models\Trx\TrxStamina;
use NexusStamina\DataTransferObjects\Stamina;

/**
 * StaminaAdapter
 *
 * TrxStamina Model と Stamina の変換を行うアダプター
 */
class StaminaAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxStamina $model): Stamina
    {
        return new Stamina(
            sysPlayerId: $model->sys_player_id,
            type: $model->type,
            currentStamina: $model->current_stamina,
            recoveryRateMultiplier: $model->recovery_rate_multiplier,
            lastRecoveryAt: $model->last_recovery_at
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxStamina>  $models
     * @return array<Stamina>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];
        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
