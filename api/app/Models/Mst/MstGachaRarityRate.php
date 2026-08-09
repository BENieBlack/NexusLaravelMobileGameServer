<?php

namespace App\Models\Mst;

/**
 * MstGachaRarityRate Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_id
 * @property int $rarity
 * @property int $rate
 */
class MstGachaRarityRate extends _BaseMst
{
    public $table = 'mst_gacha_rarity_rate';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_id',
        'rarity',
        'rate',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'rarity' => 'integer',
        'rate' => 'integer',
    ];

    public $timestamps = true;
}
