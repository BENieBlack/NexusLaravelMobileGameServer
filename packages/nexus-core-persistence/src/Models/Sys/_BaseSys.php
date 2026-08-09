<?php

namespace NexusPersistence\Models\Sys;

use Nexus\Core\Models\_BaseModel;

/**
 * _BaseSys
 * 
 * Sysデータベースのモデル基底クラス
 * システム共通データ（全シャード共通）
 */
abstract class _BaseSys extends _BaseModel implements _BaseSysInterface
{
    /**
     * システムデータベース接続を使用
     * 
     * @var string
     */
    protected $connection = 'sys';

    /**
     * Unit of Workパターンを使用しない
     * 
     * @var bool
     */
    protected bool $usesUnitOfWork = false;
}
