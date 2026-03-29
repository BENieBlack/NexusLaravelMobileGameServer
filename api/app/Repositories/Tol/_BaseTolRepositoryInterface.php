<?php

namespace App\Repositories\Tol;

use App\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseTolRepositoryInterface
 *
 * Tol配下のRepository用のインターフェース
 * ツール向けデータをメモリから取得する機能を定義
 */
interface _BaseTolRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * データベースまたはメモリから管理者データを取得
     *
     * 管理者向けマスターデータを取得し、
     * メモリにキャッシュされている場合はそれを返す
     * プレイヤーIDは不要（全体データ）
     *
     * @return Collection 管理者データのコレクション
     */
    public function queryOrMemory(): Collection;
}
