<?php

namespace App\Models\Trx;

/**
 * TrxGacha Model
 * 
 * ガチャプレイヤー進行状況モデル
 * 
 * @property int $id
 * @property int $sys_player_id
 * @property string $mst_gacha_id
 * @property int $current_step
 * @property int $daily_draw_count
 * @property string|null $daily_reset_at
 * @property int $total_draw_count
 * @property string|null $total_reset_at
 * @property bool $is_delete
 */
class TrxGacha extends _BaseTrx
{
    protected $table = 'trx_gacha';

    public $incrementing = true;
    protected $keyType = 'int';

    protected string $selectKey = 'sys_player_id';

    protected array $uniqueKeys = ['sys_player_id', 'mst_gacha_id'];

    protected $fillable = [
        'sys_player_id',
        'mst_gacha_id',
        'current_step',
        'daily_draw_count',
        'daily_reset_at',
        'total_draw_count',
        'total_reset_at',
        'is_delete',
    ];

    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'current_step' => 'integer',
        'daily_draw_count' => 'integer',
        'daily_reset_at' => 'immutable_datetime',
        'total_draw_count' => 'integer',
        'total_reset_at' => 'immutable_datetime',
        'is_delete' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    public function getMstGachaId(): string
    {
        return $this->getAttribute('mst_gacha_id');
    }

    public function getCurrentStep(): int
    {
        return $this->getAttribute('current_step');
    }

    public function getDailyDrawCount(): int
    {
        return $this->getAttribute('daily_draw_count');
    }

    public function getTotalDrawCount(): int
    {
        return $this->getAttribute('total_draw_count');
    }
}
