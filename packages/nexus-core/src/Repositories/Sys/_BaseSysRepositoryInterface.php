<?php

namespace Nexus\Core\Repositories\Sys;

use Nexus\Core\Models\Sys\_BaseSys;
use Nexus\Core\Repositories\_BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Support\CustomCollection;

/**
 * _BaseSysRepositoryInterface
 *
 * SysデータRepository用のインターフェース
 * メモリキャッシュを基本とし、一部のRepositoryでRedisキャッシュも使用
 * 
 * @template T of _BaseSys
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
