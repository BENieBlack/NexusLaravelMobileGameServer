<?php

namespace App\Repositories\Trx;

use Nexus\Core\Repositories\Trx\_BaseTrxRepositoryInterface as PersistenceBaseTrxRepositoryInterface;

/**
 * _BaseTrxRepositoryInterface
 *
 * TrxデータRepository用のインターフェース
 * メモリキャッシュのみを使用し、ユニークキーで管理
 *
 * @template T of \App\Models\Trx\_BaseTrxInterface
 */
interface _BaseTrxRepositoryInterface extends PersistenceBaseTrxRepositoryInterface {}
