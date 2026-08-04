<?php

namespace NexusPersistence\Repositories\Log;

use NexusPersistence\Models\Log\_BaseLogInterface;
use NexusPersistence\Repositories\_BaseRepositoryInterface;
use NexusPersistence\Support\CustomCollection;

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
     * @return CustomCollection<int, T> ログデータのコレクション
     */
    public function queryOrMemory(): CustomCollection;

    /**
     * IDでログレコードを取得
     *
     * @param int $logRecordId
     * @return T|null
     */
    public function getById(int $logRecordId);
}
