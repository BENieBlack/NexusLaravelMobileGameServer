<?php

namespace App\Models\Mst;

/**
 * MstGachaPrize Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_id
 * @property int $rarity
 * @property string $content_type
 * @property string $content_id
 * @property int $amount
 * @property int $weight
 * @property bool $is_pickup
 * @property bool $is_active
 */
class MstGachaPrize extends _BaseMst
{
    public $table = 'mst_gacha_prize';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_id',
        'rarity',
        'content_type',
        'content_id',
        'amount',
        'weight',
        'is_pickup',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'rarity' => 'integer',
        'amount' => 'integer',
        'weight' => 'integer',
        'is_pickup' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;
}
