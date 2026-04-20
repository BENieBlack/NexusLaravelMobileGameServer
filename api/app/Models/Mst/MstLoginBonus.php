<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstLoginBonus Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property int $day
 * @property int $loop_days
 * @property bool $is_active
 */
class MstLoginBonus extends _BaseMst
{
    public $table = 'mst_login_bonus';

    public $incrementing = false;
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'day',
        'loop_days',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'day' => 'integer',
        'loop_days' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * ログインボーナス報酬内容とのリレーション
     *
     * @return HasMany
     */
    public function contents(): HasMany
    {
        return $this->hasMany(MstLoginBonusContent::class, 'mst_login_bonus_id', 'id')
                    ->orderBy('sort_order', 'asc');
    }
}
