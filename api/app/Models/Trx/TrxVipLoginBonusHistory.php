<?php

namespace App\Models\Trx;

use Carbon\CarbonImmutable;
use Nexus\Core\Models\Trx\_BaseTrx;

/**
 * TrxVipLoginBonusHistory
 *
 * VIPログインボーナス受け取り履歴
 *
 * @property int $id
 * @property int $sys_player_id プレイヤーID
 * @property string $mst_vip_login_bonus_id VIPログインボーナスID
 * @property int $day 受け取った日数
 * @property int $vip_level 受け取り時のVIPレベル
 * @property string $received_at 受け取り日時（UTC）
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TrxVipLoginBonusHistory extends _BaseTrx
{
    protected $table = 'trx_vip_login_bonus_history';

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_vip_login_bonus_id',
        'day',
        'vip_level',
        'received_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'day' => 'integer',
        'vip_level' => 'integer',
    ];
}
