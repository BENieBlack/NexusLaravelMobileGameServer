<?php

namespace App\Models\Log;

class LogPlayer extends _BaseLog
{
        protected $table = 'log_player';

        protected $casts = [
            'id' => 'integer',
            'unique_request_id' => 'string',
            'sys_player_id' => 'integer',
            'before_level' => 'integer',
            'before_level_exp' => 'integer',
            'after_level' => 'integer',
            'after_level_exp' => 'integer',
            'system_at' => 'datetime',
        ];

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'before_level',
        'before_level_exp',
        'after_level',
        'after_level_exp',
        'system_at',
    ];
}
