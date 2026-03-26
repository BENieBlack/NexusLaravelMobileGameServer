<?php

namespace App\Models\Log;

class LogUnit extends _BaseLog
{
        protected $table = 'log_unit';

        protected $casts = [
            'id' => 'integer',
            'unique_request_id' => 'string',
            'sys_player_id' => 'integer',
            'trx_unit_id' => 'integer',
            'mst_unit_id' => 'integer',
            'before_grade' => 'integer',
            'after_grade' => 'integer',
            'before_level' => 'integer',
            'after_level' => 'integer',
            'before_level_exp' => 'integer',
            'after_level_exp' => 'integer',
            'system_at' => 'datetime',
        ];

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'trx_unit_id',
        'mst_unit_id',
        'before_grade',
        'after_grade',
        'before_level',
        'after_level',
        'before_level_exp',
        'after_level_exp',
        'system_at',
    ];
}
