<?php

namespace Nexus\Core\Models\Log;

use Nexus\Core\Models\_BaseModel;

/**
 * _BaseLog
 * 
 * Logデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるログデータ
 */
abstract class _BaseLog extends _BaseModel implements _BaseLogInterface
{
    /**
     * ログテーブルは log データベース接続を使用
     * 
     * @var string
     */
    protected $connection = 'log';

    /**
     * Unit of Workパターンを使用
     * 
     * @var bool
     */
    protected bool $usesUnitOfWork = true;

    /**
     * タイムスタンプを使用
     * 
     * created_at: レコード作成日時
     * updated_at: レコード更新日時（データ修正時に自動更新）
     * 
     * @var bool
     */
    public $timestamps = true;
}
