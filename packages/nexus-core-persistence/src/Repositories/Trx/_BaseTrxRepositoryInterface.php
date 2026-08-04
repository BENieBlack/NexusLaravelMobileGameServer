<?php

namespace NexusPersistence\Repositories\Trx;

use NexusPersistence\Models\Trx\_BaseTrxInterface;
use NexusPersistence\Repositories\_BaseRepositoryInterface;
use NexusPersistence\Support\CustomCollection;

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
     * @return CustomCollection<string, T> データのコレクション
     */
    public function queryOrMemory(): CustomCollection;

    /**
     * データベースまたはメモリからデータを取得（Collection形式）
     * ユニークキーでkeyByされたCollectionを返す
     *
     * @param int $sysPlayerId
     * @return CustomCollection<string, T>
     */
    public function getMapBySysPlayerId(int $sysPlayerId): CustomCollection;

    /**
     * データベースまたはメモリからデータを取得（配列形式）
     * 値のみの配列を返す
     *
     * @param int $sysPlayerId
     * @return array<T>
     */
    public function getBySysPlayerId(int $sysPlayerId): array;
}
