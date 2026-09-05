<?php

namespace App\Repositories\Log;

use App\Persistence\ApiSession;
use Nexus\Core\Repositories\Log\_BaseLogRepository as PersistenceBaseLogRepository;
use NexusPitr\Logger\ShardMapper;
use NexusUnitOfWork\Traits\UsesUnitOfWork;

/**
 * _BaseLogRepository
 *
 * ログRepositoryの基底クラス
 * ログテーブルはINSERT ONLYのため、setModelメソッドでINSERTのみ実行
 * プレイヤーIDはApiSessionから自動的に取得される
 *
 * @template T of \Nexus\Core\Models\Log\_BaseLog
 *
 * @extends PersistenceBaseLogRepository<T>
 *
 * @implements _BaseLogRepositoryInterface<T>
 */
abstract class _BaseLogRepository extends PersistenceBaseLogRepository implements _BaseLogRepositoryInterface
{
    use UsesUnitOfWork;

    /**
     * ログイン中プレイヤーの割り当てシャードに対応するLogDB接続を返す
     *
     * ログは対になるTrxシャードと同じ番号のLogDBへ書く（trx2 → log2）
     */
    protected static function resolveShardConnection(): ?string
    {
        if (! ApiSession::hasSysPlayerId()) {
            return null;
        }

        return ShardMapper::resolveLogConnection(ApiSession::resolveConnectionName('trx'));
    }

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
