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
 * @template T of \Nexus\Core\Models\Trx\_BaseTrx
 *
 * @extends PersistenceBaseTrxRepository<T>
 *
 * @implements _BaseTrxRepositoryInterface<T>
 */
abstract class _BaseTrxRepository extends PersistenceBaseTrxRepository implements _BaseTrxRepositoryInterface
{
    use UsesUnitOfWork;

    /**
     * ログイン中プレイヤーの割り当てシャードを返す
     *
     * プレイヤーが居ない文脈（コンソール・バッチ等）だけ null を返す。
     * ログイン中プレイヤーに割り当てが無い場合は例外をそのまま投げる。
     * 既定接続へ退避すると、書き込みと読み出しが別のDBに向いて
     * 「行が消えた」ように見えるだけで、原因が分からなくなる。
     */
    protected static function resolveShardConnection(): ?string
    {
        if (! ApiSession::hasSysPlayerId()) {
            return null;
        }

        return ApiSession::resolveConnectionName('trx');
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
