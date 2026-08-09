<?php

namespace App\Models\Sys;

use Nexus\Core\Models\Sys\_BaseSys as PersistenceBaseSys;

/**
 * _BaseSys
 *
 * Sysデータベースのモデル基底クラス
 * システム共通データ（全シャード共通）
 */
abstract class _BaseSys extends PersistenceBaseSys implements _BaseSysInterface
{
    // App-specific customizations can go here
}
