<?php

namespace App\Repositories;

use Nexus\Core\Repositories\_BaseRepository as PersistenceBaseRepository;

/**
 * _BaseRepository
 *
 * 全てのRepositoryの基底クラス
 * モデルのメモリキャッシュとQueryManager登録の共通処理を提供
 *
 * @template TKey of array-key
 * @template TModel of object
 *
 * @extends PersistenceBaseRepository<TKey, TModel>
 */
abstract class _BaseRepository extends PersistenceBaseRepository implements _BaseRepositoryInterface {}
