<?php

namespace App\Models\Log;

class LogAccess extends _BaseLog
{
    protected $table = 'log_access';

    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'method' => 'string',
        'endpoint' => 'string',
        'request_header' => 'array',
        'request_body' => 'array',
        'response_header' => 'array',
        'response_body' => 'array',
        'status_code' => 'integer',
    ];

    protected $fillable = [
        'id',
        'unique_request_id',
        'method',
        'endpoint',
        'sys_player_id',
        'request_header',
        'request_body',
        'response_header',
        'response_body',
        'status_code',
        'system_at',
    ];
}
