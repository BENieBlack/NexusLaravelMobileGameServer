<?php

namespace App\Repositories\Sys;

use App\Repositories\_BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * _BaseSysRepositoryInterface
 *
 * SysデータRepository用のインターフェース
 * メモリキャッシュを基本とし、一部のRepositoryでRedisキャッシュも使用
 */
interface _BaseSysRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * IDでモデルを取得
     *
     * @param int $id
     * @return Model|null
     */
    public function selectById(int $id): ?Model;
}
