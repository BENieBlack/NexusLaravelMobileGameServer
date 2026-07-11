<?php

namespace App\Repositories\Sys;

use LaravelPersistence\Repositories\Sys\_BaseSysRepository as PersistenceBaseSysRepository;
use LaravelUnitOfWork\Traits\UsesUnitOfWork;

/**
 * _BaseSysRepository
 * 
 * Sysデータベースのリポジトリ基底クラス
 * キャッシュ機能を含む共通のCRUD操作を実装
 * 
 * @template T of \App\Models\Sys\_BaseSysInterface
 * @implements _BaseSysRepositoryInterface<T>
 */
abstract class _BaseSysRepository extends PersistenceBaseSysRepository implements _BaseSysRepositoryInterface
{
    use UsesUnitOfWork;
}
