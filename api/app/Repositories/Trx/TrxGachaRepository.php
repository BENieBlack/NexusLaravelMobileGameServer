<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxGacha;

/**
 * TrxGachaRepository
 *
 * ガチャプレイヤー進行状況Repository
 * 
 * @extends _BaseTrxRepository<TrxGacha>
 */
class TrxGachaRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxGacha::class;

    /**
     * ユニークキー（sys_player_id, mst_gacha_id の複合キー）
     *
     * @var array<string>
     */
    protected array $uniqueKeys = ['sys_player_id', 'mst_gacha_id'];
}
