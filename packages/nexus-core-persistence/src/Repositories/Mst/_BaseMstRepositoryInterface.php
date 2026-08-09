<?php

namespace NexusPersistence\Repositories\Mst;

use Nexus\Core\Models\Mst\_BaseMstInterface;
use Nexus\Core\Repositories\_BaseRepositoryInterface;
use Nexus\Core\Support\CustomCollection;

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
