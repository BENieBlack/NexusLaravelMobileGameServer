<?php

namespace App\Repositories\Sys;

use LaravelPersistence\Repositories\Sys\_BaseSysRepositoryInterface as PersistenceBaseSysRepositoryInterface;

/**
 * _BaseSysRepositoryInterface
 *
 * SysデータRepository用のインターフェース
 * メモリキャッシュを基本とし、一部のRepositoryでRedisキャッシュも使用
 * 
 * @template T of \App\Models\Sys\_BaseSysInterface
 */
interface _BaseSysRepositoryInterface extends PersistenceBaseSysRepositoryInterface
{
}
