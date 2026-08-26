<?php

namespace Nexus\Core\Repositories;

use Nexus\Core\Support\CustomCollection;

/**
 * _BaseRepository
 *
 * 全てのRepositoryの基底クラス
 * モデルのメモリキャッシュとQueryManager登録の共通処理を提供
 * 
 * キャッシュのキーはサブクラスごとに体系が違う（Mstはid、Trxはユニークキーの
 * 連結文字列、Logは連番）ため、キーの型もテンプレートで受け取る。
 * 
 * @template TKey of array-key
 * @template TModel of object
 */
abstract class _BaseRepository implements _BaseRepositoryInterface
{
    /** @var class-string<TModel> */
    protected string $modelClass;

    /**
     * ユニークキーの配列
     * サブクラスでオーバーライドして独自のユニークキーを定義
     * 例: ['id'], ['sys_player_id', 'mst_item_id']
     *
     * @var array<string>
     */
        /** @var list<string> */
        protected array $uniqueKeys = ['id'];

    /**
     * メモリキャッシュされたモデルのコレクション
     * データベースから取得したモデルをメモリにキャッシュし、
     * 同一リクエスト内での重複クエリを防ぐ
     *
     * @var CustomCollection<TKey, TModel>|null
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
     * @var array<array-key, TModel>
     */
    protected array $modelQueue = [];

    /**
     * DELETE対象のモデルキュー
     *
     * @var array<array-key, TModel>
     */
    protected array $deleteQueue = [];

    /**
     * 溜め込んだモデルを取得（QueryManagerから呼ばれる）
     *
     * @return array<array-key, TModel>
     */
    public function getQueuedModels(): array
    {
        return $this->modelQueue;
    }

    /**
     * 削除対象のモデルを取得（QueryManagerから呼ばれる）
     *
     * @return array<array-key, TModel>
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
     * @return TModel|null
     */
    protected function findCachedModel(string|int $key)
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
     * @param CustomCollection<int, TKey>|null $keys 取得したいモデルのキーのコレクション（nullの場合は全て）
     * @return CustomCollection<TKey, TModel>
     */
    protected function findCachedModels(?CustomCollection $keys = null): CustomCollection
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
     * @param TModel $model
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
     * @param CustomCollection<TKey, TModel> $models
     * @return void
     */
    protected function setModels(CustomCollection $models): void
    {
        foreach ($models as $model) {
            $this->setModel($model);
        }
    }

    /**
     * 論理削除（is_delete=trueを立てる）
     *
     * 実体はUPDATEなのでmodelQueueに溜め込む。行はDBに残り続けるため、
     * 実際に消すには後段でhardDeleteModel()を呼ぶ必要がある。
     *
     * is_deleteカラムを持つのはtrx系テーブルのみ。sys系は論理削除できないため
     * hardDeleteModel()を使うこと。
     *
     * @param TModel $model
     * @return void
     */
    public function softDeleteModel($model): void
    {
        // 削除フラグをONにする（is_deleteカラムが存在する場合）
        try {
            $model->setAttribute('is_delete', true);
        } catch (\Exception $e) {
            // is_deleteカラムが存在しない場合は無視
        }

        // 論理削除はUPDATE処理なので、setModelを呼び出してmodelQueueに追加
        $this->setModel($model);

        // 削除済みの行は以降の読み取りに出てはいけないのでキャッシュから外す
        // （modelQueueには残るのでUPDATEは実行される）
        $this->forgetCachedModel($model);
    }

    /**
     * 物理削除（DELETE文で行を消す）
     *
     * deleteQueueに溜め込み、フラッシュ時にDELETEが実行される。
     *
     * @param TModel $model
     * @return void
     */
    public function hardDeleteModel($model): void
    {
        $uniqueKey = implode(':', array_map(fn($key) => $model->getAttribute($key), $this->getUniqueKeys()));
        $this->deleteQueue[$uniqueKey] = $model;

        // 削除する行は以降の読み取りに出てはいけないのでキャッシュから外す
        $this->forgetCachedModel($model);
    }

    /**
     * 読み取りキャッシュからモデルを取り除く
     *
     * @param TModel $model
     * @return void
     */
    protected function forgetCachedModel($model): void
    {
        if ($this->models === null) {
            return;
        }

        // DBから取り直したインスタンスで削除される場合があるため、
        // 同一性ではなくユニークキーの値で突き合わせる
        $values = array_map(fn($key) => $model->getAttribute($key), $this->getUniqueKeys());

        $keysToForget = [];

        foreach ($this->models as $key => $cached) {
            $cachedValues = array_map(fn($uniqueKey) => $cached->getAttribute($uniqueKey), $this->getUniqueKeys());

            if ($cachedValues === $values) {
                $keysToForget[] = $key;
            }
        }

        foreach ($keysToForget as $key) {
            $this->models->forget($key);
        }
    }

    /**
     * データベースまたはメモリからデータを取得
     * サブクラスで実装必須
     *
     * @return CustomCollection<TKey, TModel>
     */
    abstract public function queryOrMemory(): CustomCollection;

    /**
     * データベースまたはメモリからデータを取得（CustomCollection版）
     * パフォーマンス最適化されたCustomCollectionを返す
     *
     * @return CustomCollection<TKey, TModel>
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
