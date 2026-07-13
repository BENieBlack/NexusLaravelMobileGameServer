<?php

namespace LaravelPersistence\Models\Mst;

use LaravelPersistence\Models\_BaseModel;

/**
 * _BaseMst
 * 
 * Mstデータベースのモデル基底クラス
 * マスターデータ（読み取り専用）
 */
abstract class _BaseMst extends _BaseModel implements _BaseMstInterface
{
    /**
     * マスターデータベース接続を使用
     * 
     * @var string
     */
    protected $connection = 'mst';

    /**
     * Unit of Workパターンを使用しない（読み取り専用）
     * 
     * @var bool
     */
    protected bool $usesUnitOfWork = false;

    /**
     * deploy_keyをfillableに追加
     * サブクラスで追加のfillableカラムを定義する場合は、
     * このカラムも含めてマージする必要があります
     *
     * @var array
     */
    protected $fillable = [
        'deploy_key',
    ];
}
