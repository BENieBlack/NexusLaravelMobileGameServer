<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexus\Core\Models\Mst\_BaseMst;

/**
 * MstVipLoginBonus
 *
 * VIPログインボーナス設定マスターデータ
 *
 * @property string $id VIPログインボーナスID
 * @property int $vip_level 対象VIPレベル
 * @property int $loop_days ループ日数
 * @property bool $is_active 有効フラグ
 * @property string|null $start_at 開始日時（UTC）
 * @property string|null $end_at 終了日時（UTC）
 * @property string $created_at
 * @property string $updated_at
 */
class MstVipLoginBonus extends _BaseMst
{
    protected $connection = 'mst';

    protected $table = 'mst_vip_login_bonus';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'vip_level',
        'loop_days',
        'is_active',
        'start_at',
        'end_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'vip_level' => 'integer',
        'loop_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * VIPログインボーナスコンテンツとのリレーション
     *
     * @return HasMany<MstVipLoginBonusContent, $this>
     */
    /**
     * @return HasMany<MstVipLoginBonusContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(MstVipLoginBonusContent::class, 'mst_vip_login_bonus_id', 'id');
    }

    /**
     * 有効なVIPログインボーナスのみ取得するスコープ
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            });
    }

    /**
     * VIPレベルでフィルタするスコープ
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForVipLevel(Builder $query, int $vipLevel): Builder
    {
        return $query->where('vip_level', $vipLevel);
    }
}
