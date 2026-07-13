<?php

namespace NexusPersistence\Repositories\Log;

use NexusPersistence\Models\Log\_BaseLogInterface;
use NexusPersistence\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseLogRepositoryInterface
 *
 * LogデータRepository用のインターフェース
 * ログはINSERT ONLYでキャッシュなし
 * 
 * @template T of _BaseLogInterface
 */
interface _BaseLogRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * データベースまたはメモリからログデータを取得
     *
     * コンストラクタまたはApiSessionで設定されたプレイヤーIDを基にログデータを取得し、
     * メモリにキャッシュされている場合はそれを返す
     *
     * @return Collection<int, T> ログデータのコレクション
     */
    public function queryOrMemory(): Collection;

    /**
     * IDでログレコードを取得
     *
     * @param int $logRecordId
     * @return T|null
     */
    public function getById(int $logRecordId);
}
