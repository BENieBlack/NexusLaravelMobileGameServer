<?php

namespace App\Repositories\Log;

use NexusPersistence\Repositories\Log\_BaseLogRepositoryInterface as PersistenceBaseLogRepositoryInterface;

/**
 * _BaseLogRepositoryInterface
 *
 * LogデータRepository用のインターフェース
 * ログはINSERT ONLYでキャッシュなし
 * 
 * @template T of \App\Models\Log\_BaseLogInterface
 */
interface _BaseLogRepositoryInterface extends PersistenceBaseLogRepositoryInterface
{
}
