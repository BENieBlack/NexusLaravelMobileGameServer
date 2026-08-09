<?php

namespace Nexus\Core\Repositories;

/**
 * _BaseRepositoryInterface
 * 
 * 全てのRepositoryが実装すべき基底インターフェース
 * Unit of Work パターンに基づく共通メソッドを定義
 */
interface _BaseRepositoryInterface
{
    /**
     * モデルをキャッシュに保存し、QueryManagerのキューに追加
     *
     * @param mixed $model
     * @return void
     */
    public function setModel($model): void;

    /**
     * 溜め込んだモデルキューを取得（QueryManagerから呼ばれる）
     *
     * @return array
     */
    public function getQueuedModels(): array;

    /**
     * 削除対象のモデルキューを取得（QueryManagerから呼ばれる）
     *
     * @return array
     */
    public function getQueuedDeleteModels(): array;

    /**
     * モデルキューをクリア
     *
     * @return void
     */
    public function clearQueue(): void;

    /**
     * データベース接続名を取得
     *
     * @return string
     */
    public function getConnection(): string;

    /**
     * データベース接続名を設定
     *
     * @param string $connection
     * @return void
     */
    public function setConnection(string $connection): void;

    /**
     * テーブル名を取得
     *
     * @return string
     */
    public function getTableName(): string;

    /**
     * is_delete=trueでマークされたレコードを物理削除
     *
     * queryOrMemory()で取得したキャッシュから is_delete=true のレコードを
     * フィルタリングし、deleteQueueに追加して物理削除を実行
     *
     * 物理削除は危険な操作なので、_BaseRepositoryで統一的に実装し、
     * サブクラスでの挙動変更を防ぐ
     *
     * @return void
     */
    public function terminate(): void;
}
