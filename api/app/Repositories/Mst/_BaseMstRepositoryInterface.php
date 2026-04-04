<?php

namespace App\Repositories\Mst;

use App\Models\Mst\_BaseMstInterface;
use App\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseMstRepositoryInterface
 * 
 * マスターデータRepository用のインターフェース
 * マスターデータは読み取り専用でRedisキャッシュを使用
 * 
 * @template T of _BaseMstInterface
 */
interface _BaseMstRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * IDでマスターレコードを取得
     * 
     * @param int|string $mstRecordId
     * @return T|null
     */
    public function selectById($mstRecordId);
}
