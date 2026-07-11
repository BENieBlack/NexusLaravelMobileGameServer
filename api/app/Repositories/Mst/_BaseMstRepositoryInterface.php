<?php

namespace App\Repositories\Mst;

use LaravelPersistence\Repositories\Mst\_BaseMstRepositoryInterface as PersistenceBaseMstRepositoryInterface;

/**
 * _BaseMstRepositoryInterface
 * 
 * マスターデータRepository用のインターフェース
 * マスターデータは読み取り専用でRedisキャッシュを使用
 * 
 * @template T of \App\Models\Mst\_BaseMstInterface
 */
interface _BaseMstRepositoryInterface extends PersistenceBaseMstRepositoryInterface
{
}
