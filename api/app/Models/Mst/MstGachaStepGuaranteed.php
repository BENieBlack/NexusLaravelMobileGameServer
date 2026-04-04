<?php

namespace App\Models\Mst;

/**
 * MstGachaStepGuaranteed Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_step_id
 * @property int $position
 * @property int $guaranteed_count
 * @property string $selection_type
 * @property int|null $guaranteed_rarity
 * @property string|null $guaranteed_content_type
 * @property bool $is_pickup_only
 * @property bool $is_active
 */
class MstGachaStepGuaranteed extends _BaseMst
{
    public $table = 'mst_gacha_step_guaranteed';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_step_id',
        'position',
        'guaranteed_count',
        'selection_type',
        'guaranteed_rarity',
        'guaranteed_content_type',
        'is_pickup_only',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'position' => 'integer',
        'guaranteed_count' => 'integer',
        'guaranteed_rarity' => 'integer',
        'is_pickup_only' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;
}
