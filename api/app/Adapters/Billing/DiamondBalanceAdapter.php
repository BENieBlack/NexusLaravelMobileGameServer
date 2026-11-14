<?php

namespace App\Adapters\Billing;

use App\Models\Trx\TrxDiamond;
use Nexus\Core\Resource\DataTransferObjects\DiamondBalance;

/**
 * DiamondBalanceAdapter
 *
 * TrxDiamond Model と DiamondBalance の変換を行うアダプター
 */
class DiamondBalanceAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxDiamond $trxDiamond): DiamondBalance
    {
        return new DiamondBalance(
            sysPlayerId: $trxDiamond->getSysPlayerId(),
            platform: $trxDiamond->getPlatform(),
            paidAmount: $trxDiamond->getPaidAmount(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxDiamond>  $trxDiamonds
     * @return array<DiamondBalance>
     */
    public static function toDtoArray(iterable $trxDiamonds): array
    {
        $dtos = [];
        foreach ($trxDiamonds as $trxDiamond) {
            $dtos[] = self::toDto($trxDiamond);
        }

        return $dtos;
    }
}
