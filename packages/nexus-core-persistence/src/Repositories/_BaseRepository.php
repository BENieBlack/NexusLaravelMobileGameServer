<?php

namespace NexusPersistence\Repositories;

use Nexus\Core\Support\CustomCollection;

/**
 * _BaseRepository
 *
 * 全てのRepositoryの基底クラス
 * モデルのメモリキャッシュとQueryManager登録の共通処理を提供
 */
abstract class _BaseRepository implements _BaseRepositoryInterface
{
    protected string $modelClass;

    /**
     * ユニークキーの配列
     * サブクラスでオーバーライドして独自のユニークキーを定義
     * 例: ['id'], ['sys_player_id', 'mst_item_id']
     *
     * @var array<string>
     */
    protected array $uniqueKeys = ['id'];

    /**
     * メモリキャッシュされたモデルのコレクション
     * データベースから取得したモデルをメモリにキャッシュし、
     * 同一リクエスト内での重複クエリを防ぐ
     *
     * @var CustomCollection|null
     */
    protected ?CustomCollection $models = null;

    /**
     * データベース接続名
     *
     * @var string
     */
    protected string $connection;

    /**
     * キャッシュの有効期限（秒）
     *
     * @var int
     */
    protected int $cacheTtl = 3600;

    /**
     * キャッシュキーのプレフィックス
     *
     * @var string
     */
    protected string $cachePrefix = 'app';

    /**
     * キャッシュドライバー名
     *
     * @var string
     */
    protected string $cacheDriver = 'redis';

    /**
     * INSERT/UPDATE対象のモデルキュー
     *
     * @var array
     */
    protected array $modelQueue = [];

    /**
     * DELETE対象のモデルキュー
     *
     * @var array
     */
    protected array $deleteQueue = [];

    /**
     * 溜め込んだモデルを取得（QueryManagerから呼ばれる）
     *
     * @return array
     */
    public function getQueuedModels(): array
    {
        return $this->modelQueue;
    }

    /**
     * 削除対象のモデルを取得（QueryManagerから呼ばれる）
     *
     * @return array
     */
    public function getQueuedDeleteModels(): array
    {
        return $this->deleteQueue;
    }

    /**
     * モデルキューをクリア
     *
     * @return void
     */
    public function clearQueue(): void
    {
        $this->modelQueue = [];
        $this->deleteQueue = [];
    }

    /**
     * データベース接続名を取得
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * データベース接続名を設定
     *
     * @param string $connection
     * @return void
     */
    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * テーブル名を取得
     *
     * @return string
     */
    public function getTableName(): string
    {
        return (new $this->modelClass)->getTable();
    }

    /**
     * キーを指定してモデルを取得
     *
     * @param string|int $key ユニークキー
     * @return mixed|null
     */
    protected function getModel(string|int $key)
    {
        if ($this->models === null) {
            return null;
        }

        return $this->models->get((string)$key);
    }

    /**
     * キャッシュされたモデルを取得
     * キーが指定された場合は、そのキーに一致するモデルのみを返す
     *
     * @param CustomCollection|null $keys 取得したいモデルのキーのコレクション（nullの場合は全て）
     * @return CustomCollection
     */
    protected function getModels(?CustomCollection $keys = null): CustomCollection
    {
        if ($this->models === null) {
            $this->models = new CustomCollection();
        }

        // キーが指定されていない場合は全てのモデルを返す
        if ($keys === null) {
            return $this->models;
        }

        // 指定されたキーに一致するモデルのみを返す
        return $this->models->only($keys->toArray());
    }

    /**
     * モデルをキャッシュに保存
     * ユニークキーで管理し、同じキーのモデルは上書きされる
     * サブクラスでオーバーライド可能
     *
     * @param mixed $model
     * @return void
     */
    public function setModel($model): void
    {
        if ($this->models === null) {
            $this->models = new CustomCollection();
        }
        $key = implode(':', array_map(fn($uniqueKey) => $model->{$uniqueKey}, $this->getUniqueKeys()));
        $this->models->put($key, $model);
    }

    /**
     * 複数のモデルをキャッシュに保存
     *
     * @param CustomCollection $models
     * @return void
     */
    protected function setModels(CustomCollection $models): void
    {
        foreach ($models as $model) {
            $this->setModel($model);
        }
    }

    /**
     * 削除対象モデルをセットし、論理削除フラグをON（is_delete=true）にする
     * 論理削除はUPDATE処理なので、modelQueueに溜め込む
     *
     * @param mixed $model
     * @return void
     */
    protected function deleteModel($model): void
    {
        // 削除フラグをONにする（is_deleteカラムが存在する場合）
        try {
            $model->setAttribute('is_delete', true);
        } catch (\Exception $e) {
            // is_deleteカラムが存在しない場合は無視
        }

        // 論理削除はUPDATE処理なので、setModelを呼び出してmodelQueueに追加
        $this->setModel($model);
    }

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
    public function terminate(): void
    {
        // queryOrMemory()でキャッシュからデータを取得
        $allRecordCollection = $this->queryOrMemory();

        // is_delete=trueのレコードをフィルタして物理削除キューに追加
        foreach ($allRecordCollection as $record) {
            if ($record->getAttribute('is_delete') === true) {
                // ユニークキーを生成
                $uniqueKey = implode(':', array_map(fn($key) => $record->getAttribute($key), $this->getUniqueKeys()));
                
                // 削除キューに溜め込む（物理削除用）
                $this->deleteQueue[$uniqueKey] = $record;
            }
        }
    }

    /**
     * データベースまたはメモリからデータを取得
     * サブクラスで実装必須
     *
     * @return CustomCollection
     */
    abstract public function queryOrMemory(): CustomCollection;

    /**
     * データベースまたはメモリからデータを取得（CustomCollection版）
     * パフォーマンス最適化されたCustomCollectionを返す
     *
     * @return CustomCollection
     * @deprecated Use queryOrMemory() directly as it now returns CustomCollection
     */
    protected function queryOrMemoryCustom(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * ユニークキーを取得
     *
     * @return array<string>
     */
    protected function getUniqueKeys(): array
    {
        return $this->uniqueKeys;
    }
}
