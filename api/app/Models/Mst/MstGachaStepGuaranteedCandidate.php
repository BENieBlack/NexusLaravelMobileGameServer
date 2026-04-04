<?php

namespace App\Models\Mst;

/**
 * MstGachaStepGuaranteedCandidate Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_step_guaranteed_id
 * @property string $content_type
 * @property string $content_id
 * @property int $amount
 * @property int $weight
 * @property int $sort_order
 * @property bool $is_active
 */
class MstGachaStepGuaranteedCandidate extends _BaseMst
{
    public $table = 'mst_gacha_step_guaranteed_candidate';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_step_guaranteed_id',
        'content_type',
        'content_id',
        'amount',
        'weight',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'amount' => 'integer',
        'weight' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;
}
