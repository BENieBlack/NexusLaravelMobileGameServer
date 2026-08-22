<?php

namespace App\Repositories\Trx;

use App\Persistence\ApiSession;
use Nexus\Core\Repositories\Trx\_BaseTrxRepository as PersistenceBaseTrxRepository;
use NexusUnitOfWork\Traits\UsesUnitOfWork;

/**
 * _BaseTrxRepository
 *
 * Trxデータベース用のRepository基底クラス
 * ユニークキーで管理し、同じキーのモデルは上書き（最終状態を保持）
 * プレイヤーIDはApiSessionから自動的に取得される
 *
 * @template T of \Nexus\Core\Models\Trx\_BaseTrxInterface
 *
 * @extends PersistenceBaseTrxRepository<T>
 * @implements _BaseTrxRepositoryInterface<T>
 */
abstract class _BaseTrxRepository extends PersistenceBaseTrxRepository implements _BaseTrxRepositoryInterface
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

    protected static function setSysPlayerId(int $sysPlayerId): void
    {
        ApiSession::setSysPlayerId($sysPlayerId);
    }
}
