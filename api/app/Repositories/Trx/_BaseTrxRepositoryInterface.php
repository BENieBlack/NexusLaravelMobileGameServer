<?php

namespace App\Repositories\Trx;

use App\Models\Trx\_BaseTrx;
use App\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseTrxRepositoryInterface
 *
 * TrxデータRepository用のインターフェース
 * メモリキャッシュのみを使用し、ユニークキーで管理
 */
interface _BaseTrxRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * データベースまたはメモリからデータを取得
     *
     * コンストラクタまたはApiSessionで設定されたプレイヤーIDを基にデータを取得し、
     * メモリにキャッシュされている場合はそれを返す
     *
     * @return Collection データのコレクション
     */
    public function queryOrMemory(): Collection;

    /**
     * データベースまたはメモリからデータを取得（Collection形式）
     * ユニークキーでkeyByされたCollectionを返す
     *
     * @param int $sysPlayerId
     * @return Collection<string, _BaseTrx>
     */
    public function getMapBySysPlayerId(int $sysPlayerId): Collection;

    /**
     * データベースまたはメモリからデータを取得（配列形式）
     * 値のみの配列を返す
     *
     * @param int $sysPlayerId
     * @return array<_BaseTrx>
     */
    public function getBySysPlayerId(int $sysPlayerId): array;
}
