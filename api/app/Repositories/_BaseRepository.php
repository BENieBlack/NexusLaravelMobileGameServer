<?php

namespace App\Repositories;

use NexusPersistence\Repositories\_BaseRepository as PersistenceBaseRepository;

/**
 * _BaseRepository
 *
 * 全てのRepositoryの基底クラス
 * モデルのメモリキャッシュとQueryManager登録の共通処理を提供
 */
abstract class _BaseRepository extends PersistenceBaseRepository implements _BaseRepositoryInterface
{
}
