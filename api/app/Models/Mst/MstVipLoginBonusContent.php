<?php

namespace App\Models\Mst;

use Carbon\CarbonImmutable;
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
 * @property array|null $content_option 報酬オプション
 * @property int $content_quantity 報酬の基本個数
 * @property int $amount 報酬の倍率
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class MstVipLoginBonusContent extends _BaseMst
{
    protected $connection = 'mst';

    protected $table = 'mst_vip_login_bonus_content';

    protected $fillable = [
        'mst_vip_login_bonus_id',
        'day',
        'content_type',
        'content_id',
        'content_option',
        'content_quantity',
        'amount',
    ];

    protected $casts = [
        'day' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
    ];

    /**
     * VIPログインボーナスとのリレーション
     */
    public function vipLoginBonus()
    {
        return $this->belongsTo(MstVipLoginBonus::class, 'mst_vip_login_bonus_id', 'id');
    }
}
