<?php

namespace LaravelPersistence\Repositories\Trx;

use LaravelPersistence\Models\Trx\_BaseTrxInterface;
use LaravelPersistence\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseTrxRepositoryInterface
 *
 * TrxデータRepository用のインターフェース
 * メモリキャッシュのみを使用し、ユニークキーで管理
 * 
 * @template T of _BaseTrxInterface
 */
interface _BaseTrxRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * データベースまたはメモリからデータを取得
     *
     * コンストラクタまたはApiSessionで設定されたプレイヤーIDを基にデータを取得し、
     * メモリにキャッシュされている場合はそれを返す
     *
     * @return Collection<string, T> データのコレクション
     */
    public function queryOrMemory(): Collection;

    /**
     * データベースまたはメモリからデータを取得（Collection形式）
     * ユニークキーでkeyByされたCollectionを返す
     *
     * @param int $sysPlayerId
     * @return Collection<string, T>
     */
    public function getMapBySysPlayerId(int $sysPlayerId): Collection;

    /**
     * データベースまたはメモリからデータを取得（配列形式）
     * 値のみの配列を返す
     *
     * @param int $sysPlayerId
     * @return array<T>
     */
    public function getBySysPlayerId(int $sysPlayerId): array;
}
