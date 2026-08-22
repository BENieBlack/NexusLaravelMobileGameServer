<?php

namespace App\Repositories\Log;

use Nexus\Core\Repositories\Log\_BaseLogRepositoryInterface as PersistenceBaseLogRepositoryInterface;

/**
 * _BaseLogRepositoryInterface
 *
 * LogデータRepository用のインターフェース
 * ログはINSERT ONLYでキャッシュなし
 *
 * @template T of \Nexus\Core\Models\Log\_BaseLogInterface
 *
 * @extends PersistenceBaseLogRepositoryInterface<T>
 */
interface _BaseLogRepositoryInterface extends PersistenceBaseLogRepositoryInterface {}
