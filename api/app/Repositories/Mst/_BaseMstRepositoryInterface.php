<?php

namespace App\Repositories\Mst;

use Nexus\Core\Repositories\Mst\_BaseMstRepositoryInterface as PersistenceBaseMstRepositoryInterface;

/**
 * _BaseMstRepositoryInterface
 *
 * マスターデータRepository用のインターフェース
 * マスターデータは読み取り専用でRedisキャッシュを使用
 *
 * @template T of \Nexus\Core\Models\Mst\_BaseMst
 *
 * @extends PersistenceBaseMstRepositoryInterface<T>
 */
interface _BaseMstRepositoryInterface extends PersistenceBaseMstRepositoryInterface {}
