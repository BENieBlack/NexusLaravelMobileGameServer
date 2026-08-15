<?php

namespace App\Models\Log;

class LogEquipment extends _BaseLog
{
    protected $table = 'log_equipment';

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'trx_equipment_id' => 'integer',
        'mst_equipment_id' => 'string',
        'before_grade' => 'integer',
        'after_grade' => 'integer',
        'before_level' => 'integer',
        'before_level_exp' => 'integer',
        'after_level' => 'integer',
        'after_level_exp' => 'integer',
    ];

    /** @var list<string> */
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'trx_equipment_id',
        'mst_equipment_id',
        'before_grade',
        'after_grade',
        'before_level',
        'before_level_exp',
        'after_level',
        'after_level_exp',
        'system_at',
    ];
}
