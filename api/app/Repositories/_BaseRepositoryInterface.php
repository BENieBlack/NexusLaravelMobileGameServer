<?php

namespace App\Repositories;

use NexusPersistence\Repositories\_BaseRepositoryInterface as PersistenceBaseRepositoryInterface;

/**
 * _BaseRepositoryInterface
 *
 * 全てのRepositoryが実装すべき基底インターフェース
 * Unit of Work パターンに基づく共通メソッドを定義
 */
interface _BaseRepositoryInterface extends PersistenceBaseRepositoryInterface {}
