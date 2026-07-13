<?php

namespace NexusPersistence\Repositories\Sys;

use NexusPersistence\Models\Sys\_BaseSysInterface;
use NexusPersistence\Repositories\_BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * _BaseSysRepositoryInterface
 *
 * SysデータRepository用のインターフェース
 * メモリキャッシュを基本とし、一部のRepositoryでRedisキャッシュも使用
 * 
 * @template T of _BaseSysInterface
 */
interface _BaseSysRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * IDでモデルを取得
     *
     * @param int $sysRecordId
     * @return T|null
     */
    public function selectById(int $sysRecordId);
}
