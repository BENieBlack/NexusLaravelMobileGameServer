<?php

namespace App\Repositories\Trx;

use Nexus\Core\Repositories\Trx\_BaseTrxRepositoryInterface as PersistenceBaseTrxRepositoryInterface;

/**
 * _BaseTrxRepositoryInterface
 *
 * TrxデータRepository用のインターフェース
 * メモリキャッシュのみを使用し、ユニークキーで管理
 *
 * @template T of \Nexus\Core\Models\Trx\_BaseTrxInterface
 *
 * @extends PersistenceBaseTrxRepositoryInterface<T>
 */
interface _BaseTrxRepositoryInterface extends PersistenceBaseTrxRepositoryInterface {}
