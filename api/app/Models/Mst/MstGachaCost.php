<?php

namespace App\Models\Mst;

/**
 * MstGachaCost Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_id
 * @property int $draw_count
 * @property string $cost_type
 * @property string|null $cost_mst_id
 * @property int $cost_amount
 * @property bool $is_active
 */
class MstGachaCost extends _BaseMst
{
    public $table = 'mst_gacha_cost';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_id',
        'draw_count',
        'cost_type',
        'cost_mst_id',
        'cost_amount',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'draw_count' => 'integer',
        'cost_amount' => 'integer',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;
}
