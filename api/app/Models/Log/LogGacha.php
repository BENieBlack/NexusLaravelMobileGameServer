<?php

namespace App\Models\Log;

class LogGacha extends _BaseLog
{
    protected $table = 'log_gacha';

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'mst_gacha_id' => 'string',
        'result' => 'array',
    ];

    /** @var list<string> */
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'mst_gacha_id',
        'result',
        'system_at',
    ];
}
