<?php

namespace App\Models\Mst;

use Nexus\Core\Models\Mst\_BaseMst as PersistenceBaseMst;

/**
 * _BaseMst
 *
 * Mstデータベースのモデル基底クラス
 * マスターデータ（読み取り専用）
 */
abstract class _BaseMst extends PersistenceBaseMst implements _BaseMstInterface
{
    // App-specific customizations can go here
}
