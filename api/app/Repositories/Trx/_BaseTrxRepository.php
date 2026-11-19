<?php

namespace App\Repositories\Trx;

use App\Persistence\ApiSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Repositories\Trx\_BaseTrxRepository as PersistenceBaseTrxRepository;
use Nexus\Core\UnitOfWork\Traits\UsesUnitOfWork;

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

    /**
     * 行ロック（SELECT ... FOR UPDATE）付きの読み取りクエリ
     *
     * キャッシュにも更新キューにも載せず、常に DB の最新の行を読む（current read）。
     * 論理削除済みの行も返す（excludesSoftDeleted は掛からない）。
     * 呼び出し側が getIsDelete() などで判定すること。
     * 接続は getConnection() に固定する（setConnection() 済みならバッチが切り替えた
     * シャード、そうでなければログイン中プレイヤーのシャード）。
     * トランザクションの外で呼ぶとロックが即座に外れて何も守れないため、
     * 外で呼ばれたら例外にして気づけるようにする。
     *
     * @return Builder<T>
     *
     * @throws \LogicException トランザクション内でない場合
     */
    protected function selectForUpdate(): Builder
    {
        $connection = $this->getConnection();

        if (DB::connection($connection)->transactionLevel() === 0) {
            throw new \LogicException(sprintf(
                '%s: 行ロックはトランザクション内でしか効かない。トランザクションの中で呼ぶこと（接続: %s）',
                static::class,
                $connection
            ));
        }

        /** @var Builder<T> */
        return $this->modelClass::on($connection)->lockForUpdate();
    }
}
