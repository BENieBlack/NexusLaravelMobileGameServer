<?php

namespace App\Models\Trx;

use Nexus\Core\Models\Trx\_BaseTrx as PersistenceBaseTrx;

/**
 * _BaseTrx
 *
 * Trxデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるトランザクションデータ
 */
abstract class _BaseTrx extends PersistenceBaseTrx implements _BaseTrxInterface
{
    // App-specific customizations can go here
}
