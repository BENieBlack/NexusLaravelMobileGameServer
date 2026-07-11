<?php

namespace App\Repositories\Log;

use App\Persistence\ApiSession;
use LaravelPersistence\Repositories\Log\_BaseLogRepository as PersistenceBaseLogRepository;
use LaravelUnitOfWork\Traits\UsesUnitOfWork;

/**
 * _BaseLogRepository
 *
 * ログRepositoryの基底クラス
 * ログテーブルはINSERT ONLYのため、setModelメソッドでINSERTのみ実行
 * プレイヤーIDはApiSessionから自動的に取得される
 * 
 * @template T of \App\Models\Log\_BaseLogInterface
 * @implements _BaseLogRepositoryInterface<T>
 */
abstract class _BaseLogRepository extends PersistenceBaseLogRepository implements _BaseLogRepositoryInterface
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
