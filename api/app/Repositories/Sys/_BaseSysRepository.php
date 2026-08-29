<?php

namespace App\Repositories\Sys;

use App\Persistence\ApiSession;
use Nexus\Core\Repositories\Sys\_BaseSysRepository as PersistenceBaseSysRepository;
use NexusUnitOfWork\Traits\UsesUnitOfWork;

/**
 * _BaseSysRepository
 *
 * Sysデータベースのリポジトリ基底クラス
 * キャッシュ機能を含む共通のCRUD操作を実装
 *
 * @template T of \Nexus\Core\Models\Sys\_BaseSys
 *
 * @extends PersistenceBaseSysRepository<T>
 *
 * @implements _BaseSysRepositoryInterface<T>
 */
abstract class _BaseSysRepository extends PersistenceBaseSysRepository implements _BaseSysRepositoryInterface
{
    use UsesUnitOfWork;

    protected static function hasSysPlayerId(): bool
    {
        return ApiSession::hasSysPlayerId();
    }

    protected static function getSysPlayerId(): int
    {
        return ApiSession::getSysPlayerId();
    }
}
