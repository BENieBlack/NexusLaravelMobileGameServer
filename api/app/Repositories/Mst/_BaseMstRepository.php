<?php

namespace App\Repositories\Mst;

use NexusPersistence\Repositories\Mst\_BaseMstRepository as PersistenceBaseMstRepository;

/**
 * _BaseMstRepository
 *
 * マスターデータのRepository基底クラス
 * キャッシュ機能を含む読み取り専用操作を提供
 *
 * @template T of \App\Models\Mst\_BaseMstInterface
 *
 * @implements _BaseMstRepositoryInterface<T>
 */
abstract class _BaseMstRepository extends PersistenceBaseMstRepository implements _BaseMstRepositoryInterface {}
