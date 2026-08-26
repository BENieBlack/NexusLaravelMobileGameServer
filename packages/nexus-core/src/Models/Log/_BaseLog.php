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
     * 既定はnull。ログはプレイヤーのTrxシャードと対になるLogDBへ書くため、
     * 固定してしまうと全プレイヤーのログが同じシャードに集まってしまう。
     * setConnection() や ::on() で明示された場合はそちらが優先される。
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * 既定のデータベース接続名（シャードを解決できない場合の退避先）
     *
     * @var string
     */
    protected string $fallbackConnection = 'log1';

    /**
     * 使用するデータベース接続名を返す
     *
     * 優先順位は _BaseTrx と同じ（明示指定 > 割り当てシャード > 退避先）
     */
    public function getConnectionName(): string
    {
        return $this->connection ?? static::resolveShardConnection() ?? $this->fallbackConnection;
    }

    /**
     * ログイン中プレイヤーの割り当てシャードに対応するLogDB接続を返す
     *
     * アプリケーション層でオーバーライドして接続名を返す
     */
    protected static function resolveShardConnection(): ?string
    {
        return null;
    }

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
