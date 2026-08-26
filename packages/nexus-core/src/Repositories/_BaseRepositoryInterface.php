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
     * @return array<array-key, \Illuminate\Database\Eloquent\Model>
     */
    public function getQueuedModels(): array;

    /**
     * 削除対象のモデルキューを取得（QueryManagerから呼ばれる）
     *
     * @return array<array-key, \Illuminate\Database\Eloquent\Model>
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
     * 論理削除（is_delete=trueを立てる）
     *
     * 実体はUPDATEなのでmodelQueueに溜め込まれる。
     *
     * @param mixed $model
     * @return void
     */
    public function softDeleteModel($model): void;

    /**
     * 物理削除（DELETE文で行を消す）
     *
     * deleteQueueに溜め込まれ、フラッシュ時にDELETEが実行される。
     *
     * @param mixed $model
     * @return void
     */
    public function hardDeleteModel($model): void;
}
