<?php

namespace App\Models\Sys;

/**
 * テーブル単位SQLiteの配信情報。
 *
 * @property int $sys_deploy_master_id
 * @property string $table_name
 * @property string $hash
 * @property int $file_size
 * @property string $file_name
 * @property string $public_url
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
