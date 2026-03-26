<?php

namespace App\Models\Log;

class LogGacha extends _BaseLog
{
    protected $table = 'log_gacha';

    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'mst_gacha_id' => 'string',
        'result' => 'array',
        'system_at' => 'datetime',
    ];

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'mst_gacha_id',
        'result',
        'system_at',
    ];
}
