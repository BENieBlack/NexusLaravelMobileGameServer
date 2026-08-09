<?php

namespace App\Models\Mst;

/**
 * MstGachaStep Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_id
 * @property int $step_number
 * @property int $draw_count
 * @property string|null $cost_type
 * @property string|null $cost_id
 * @property int|null $cost_amount
 * @property bool $is_loop_start
 * @property bool $is_active
 */
class MstGachaStep extends _BaseMst
{
    public $table = 'mst_gacha_step';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_id',
        'step_number',
        'draw_count',
        'cost_type',
        'cost_id',
        'cost_amount',
        'is_loop_start',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'step_number' => 'integer',
        'draw_count' => 'integer',
        'cost_amount' => 'integer',
        'is_loop_start' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;
}
