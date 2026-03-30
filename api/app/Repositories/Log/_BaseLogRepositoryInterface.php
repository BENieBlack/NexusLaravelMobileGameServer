<?php

namespace App\Repositories\Log;

use App\Models\Log\_BaseLog;
use App\Repositories\_BaseRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * _BaseLogRepositoryInterface
 *
 * LogデータRepository用のインターフェース
 * ログはINSERT ONLYでキャッシュなし
 */
interface _BaseLogRepositoryInterface extends _BaseRepositoryInterface
{
    /**
     * データベースまたはメモリからログデータを取得
     *
     * コンストラクタまたはApiSessionで設定されたプレイヤーIDを基にログデータを取得し、
     * メモリにキャッシュされている場合はそれを返す
     *
     * @return Collection ログデータのコレクション
     */
    public function queryOrMemory(): Collection;

    /**
     * IDでログレコードを取得
     *
     * @param int $logRecordId
     * @return _BaseLog|null
     */
    public function getById(int $logRecordId): ?_BaseLog;
}
