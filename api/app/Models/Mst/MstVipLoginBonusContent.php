<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\Core\Models\Mst\_BaseMst;

/**
 * MstVipLoginBonusContent
 *
 * VIPログインボーナスコンテンツマスターデータ
 *
 * @property int $id
 * @property string $mst_vip_login_bonus_id VIPログインボーナスID
 * @property int $day ログイン日数
 * @property string $content_type 報酬タイプ
 * @property string $content_id 報酬ID
 * @property array<string, mixed>|null $content_option 報酬オプション
 * @property int $content_quantity 報酬の基本個数
 * @property int $amount 報酬の倍率
 * @property string $created_at
 * @property string $updated_at
 */
class MstVipLoginBonusContent extends _BaseMst
{
    protected $connection = 'mst';

    protected $table = 'mst_vip_login_bonus_content';

    /** @var list<string> */
    protected $fillable = [
        'mst_vip_login_bonus_id',
        'day',
        'content_type',
        'content_id',
        'content_option',
        'content_quantity',
        'amount',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'day' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
    ];

    /**
     * VIPログインボーナスとのリレーション
     *
     * @return BelongsTo<MstVipLoginBonus, $this>
     */
    public function vipLoginBonus(): BelongsTo
    {
        return $this->belongsTo(MstVipLoginBonus::class, 'mst_vip_login_bonus_id', 'id');
    }
}
