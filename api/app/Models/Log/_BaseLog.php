<?php

namespace App\Models\Log;

use NexusPersistence\Models\Log\_BaseLog as PersistenceBaseLog;

/**
 * _BaseLog
 *
 * Logデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるログデータ
 */
abstract class _BaseLog extends PersistenceBaseLog implements _BaseLogInterface
{
    // App-specific customizations can go here
}
