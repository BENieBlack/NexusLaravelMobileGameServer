<?php

namespace App\Repositories\Mst;

use App\Models\Mst\_BaseMst;
use App\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseMstRepositoryInterface
 * 
 * マスターデータRepository用のインターフェース
 * マスターデータは読み取り専用でRedisキャッシュを使用
 */
interface _BaseMstRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * IDでマスターレコードを取得
     * 
     * @param int|string $id
     * @return _BaseMst|null
     */
    public function selectById($id): ?_BaseMst;
}
