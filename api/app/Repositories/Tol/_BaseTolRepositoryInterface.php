<?php

namespace App\Repositories\Mst\Tol;

use App\Repositories\Mst\_BaseMstRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseMstTolAdmRepositoryInterface
 * 
 * Mst/Tol配下のAdmRepository用のインターフェース
 * 管理者向けマスターデータをメモリから取得する機能を定義
 */
interface _BaseMstTolAdmRepositoryInterface extends _BaseMstRepositoryInterface
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
