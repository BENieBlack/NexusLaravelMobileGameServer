<?php

namespace App\Models\Trx;

/**
 * TrxGachaHistory Model
 *
 * ガチャ実行履歴モデル
 *
 * @property int $id
 * @property int $sys_player_id
 * @property string $mst_gacha_id
 * @property int $draw_count
 * @property string $cost_type
 * @property string|null $cost_id
 * @property int $cost_amount
 * @property array $prizes
 * @property bool $is_delete
 */
class TrxGachaHistory extends _BaseTrx
{
    protected $table = 'trx_gacha_history';

    public $incrementing = true;

    protected $keyType = 'int';

    protected string $selectKey = 'sys_player_id';

    protected $fillable = [
        'sys_player_id',
        'mst_gacha_id',
        'draw_count',
        'cost_type',
        'cost_id',
        'cost_amount',
        'prizes',
        'is_delete',
    ];

    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'draw_count' => 'integer',
        'cost_amount' => 'integer',
        'prizes' => 'array',
        'is_delete' => 'boolean',
    ];

    public $timestamps = true;
}
