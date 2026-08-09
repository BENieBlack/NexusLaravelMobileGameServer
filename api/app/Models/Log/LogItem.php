<?php

namespace App\Models\Log;

class LogItem extends _BaseLog
{
    protected $table = 'log_item';

    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'mst_item_id' => 'string',
        'before_amount' => 'integer',
        'after_amount' => 'integer',
        'system_at' => 'datetime',
    ];

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'mst_item_id',
        'before_amount',
        'after_amount',
        'system_at',
    ];
}
