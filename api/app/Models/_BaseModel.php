<?php

namespace App\Models;

use LaravelPersistence\Models\_BaseModel as PersistenceBaseModel;

/**
 * _BaseModel
 * 
 * 全てのモデルの最上位基底クラス
 * 共通の振る舞いとヘルパーメソッドを提供
 */
abstract class _BaseModel extends PersistenceBaseModel implements _BaseModelInterface
{
    // App-specific customizations can go here
}
