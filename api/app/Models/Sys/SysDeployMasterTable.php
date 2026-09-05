<?php

namespace App\Models\Sys;

/**
 * テーブル単位SQLiteの配信情報。
 */
class SysDeployMasterTable extends _BaseSys
{
    protected $table = 'sys_deploy_master_table';

    protected $fillable = [
        'sys_deploy_master_id',
        'table_name',
        'hash',
        'file_size',
        'file_name',
        'public_url',
    ];

    protected $casts = [
        'sys_deploy_master_id' => 'integer',
        'file_size' => 'integer',
    ];
}
