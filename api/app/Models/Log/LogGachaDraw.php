<?php

namespace App\Models\Log;

/**
 * LogGachaDraw Model
 *
 * ガチャ実行ログ（log_action_gacha_draw）。1 回の抽選実行につき 1 行。
 * `result` は景品一覧の JSON 文字列として持つ（呼び出し側が json_encode して渡す）。
 */
class LogGachaDraw extends _BaseLog
{
    protected $table = 'log_action_gacha_draw';

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'mst_gacha_id' => 'string',
        'draw_count' => 'integer',
        'cost_type' => 'string',
        'cost_amount' => 'integer',
    ];

    /** @var list<string> */
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'mst_gacha_id',
        'draw_count',
        'cost_type',
        'cost_mst_id',
        'cost_amount',
        'result',
        'system_at',
    ];
}
