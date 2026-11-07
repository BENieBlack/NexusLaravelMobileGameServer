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
    public static function toDto(TrxStamina $trxStamina): Stamina
    {
        return new Stamina(
            sysPlayerId: $trxStamina->sys_player_id,
            type: $trxStamina->type,
            currentStamina: $trxStamina->current_stamina,
            recoveryRateMultiplier: $trxStamina->recovery_rate_multiplier,
            lastRecoveryAt: $trxStamina->last_recovery_at
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxStamina>  $trxStaminas
     * @return array<Stamina>
     */
    public static function toDtoArray(iterable $trxStaminas): array
    {
        $dtos = [];
        foreach ($trxStaminas as $trxStamina) {
            $dtos[] = self::toDto($trxStamina);
        }

        return $dtos;
    }
}
