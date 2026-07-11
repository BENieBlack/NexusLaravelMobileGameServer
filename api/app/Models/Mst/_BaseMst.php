<?php

namespace App\Models\Mst;

use LaravelPersistence\Models\Mst\_BaseMst as PersistenceBaseMst;

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
