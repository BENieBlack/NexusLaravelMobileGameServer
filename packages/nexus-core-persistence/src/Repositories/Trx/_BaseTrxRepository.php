<?php

namespace NexusPersistence\Repositories\Trx;

use NexusPersistence\Models\Trx\_BaseTrx;
use NexusPersistence\Models\Trx\_BaseTrxInterface;
use NexusPersistence\Repositories\_BaseRepository;
use NexusPersistence\Support\CustomCollection;
use NexusUtilities\ClockUtility;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;

/**
 * _BaseTrxRepository
 *
 * Trxデータベース用のRepository基底クラス
 * ユニークキーで管理し、同じキーのモデルは上書き（最終状態を保持）
 * プレイヤーIDはApiSessionから自動的に取得される
 * 
 * @template T of _BaseTrxInterface
 * @implements _BaseTrxRepositoryInterface<T>
 */
abstract class _BaseTrxRepository extends _BaseRepository implements _BaseTrxRepositoryInterface
{
    protected string $selectKey = 'sys_player_id';

    /**
     * データベース接続名（trx1, trx2など）
     * サブクラスでオーバーライド可能
     *
     * @var string
     */
    protected string $connection = 'trx';

    /**
     * キャッシュされたプレイヤーID（パフォーマンス最適化）
     * PlayerSessionResolverから取得した値を保持し、毎回app()を呼ばないようにする
     *
     * @var int|null
     */
    private ?int $cachedSysPlayerId = null;

    /**
     * 各モデルの変更前の状態を保持（ログ記録用）
     * キー: ユニークキー, 値: オリジナル属性の配列
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $originalStateArray = [];

    /**
     * 新規モデル用の一時IDカウンター
     * モデルのIDがnullの場合に一意なキーを生成するために使用
     *
     * @var int
     */
    private int $newModelCounter = 0;

    /**
     * プレイヤーIDを取得（内部用）
     * PlayerSessionResolverから自動的に取得し、インスタンス内でキャッシュする
     * 
     * パフォーマンス最適化:
     * - 初回呼び出し時にPlayerSessionResolverから取得してキャッシュ
     * - 2回目以降はキャッシュされた値を返す（app()の呼び出しを回避）
     *
     * @return int プレイヤーID
     * @throws \RuntimeException プレイヤーIDが取得できない場合
     */
    protected function getCachedSysPlayerId(): int
    {
        // キャッシュがあればそれを返す（高速パス）
        if ($this->cachedSysPlayerId !== null) {
            return $this->cachedSysPlayerId;
        }

        // PlayerSessionResolverから取得してキャッシュ
        if (static::hasSysPlayerId()) {
            $this->cachedSysPlayerId = static::getSysPlayerId();
            return $this->cachedSysPlayerId;
        }

        // PlayerSessionResolverが未設定の場合はエラー
        throw new \RuntimeException(
            'Player ID is not available. Make sure authentication middleware is applied.'
        );
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

    /**
     * データベースまたはメモリキャッシュからデータを取得
     * キャッシュがなければsys_player_idで検索してキャッシュに保存
     * キャッシュがあればキャッシュを返す
     * プレイヤーIDはPlayerSessionResolverから自動的に取得される
     *
     * @return CustomCollection<string, T>
     * @throws \RuntimeException プレイヤーIDが取得できない場合
     */
    public function queryOrMemory(): CustomCollection
    {
        // メモリキャッシュにデータがあればそれを返す
        if ($this->models !== null && $this->models->isNotEmpty()) {
            return $this->models;
        }

        // プレイヤーIDを取得（PlayerSessionResolver優先、なければ$sysPlayerIdフィールド）
        $sysPlayerId = $this->getCachedSysPlayerId();

        // キャッシュが空の場合、データベースから取得
        /** @var _BaseTrx $instance */
        $instance = new $this->modelClass();
        
        // sys_player_idで検索してユニークキーでkeyByしてキャッシュに保存
        $records = $instance::where($this->selectKey, $sysPlayerId)
            ->get()
            ->keyBy(function ($record) {
                // ユニークキーのカラム値を連結してキーを生成
                return implode(':', array_map(fn($key) => $record->{$key}, $this->getUniqueKeys()));
            });

        $this->models = new CustomCollection($records->all());

        return $this->models;
    }

    /**
     * データベースまたはメモリからデータを取得（Collection形式）
     * ユニークキーでkeyByされたCollectionを返す
     *
     * @param int $sysPlayerId
     * @return CustomCollection<string, T>
     */
    public function getMapBySysPlayerId(int $sysPlayerId): CustomCollection
    {
        // PlayerSessionResolverにプレイヤーIDを設定
        static::setSysPlayerId($sysPlayerId);
        
        // queryOrMemory()でキャッシュから取得（フィルタ不要、全件返す）
        return $this->queryOrMemory();
    }

    /**
     * データベースまたはメモリからデータを取得（配列形式）
     * 値のみの配列を返す
     *
     * @param int $sysPlayerId
     * @return array<T>
     */
    public function getBySysPlayerId(int $sysPlayerId): array
    {
        // PlayerSessionResolverにプレイヤーIDを設定
        static::setSysPlayerId($sysPlayerId);
        
        // queryOrMemory()でキャッシュから取得して、values()で配列に変換
        return $this->queryOrMemory()->values()->all();
    }

    /**
     * モデルをセットし、内部キューに溜め込む
     * ユニークキーで管理し、同じキーは上書き
     *
     * @param _BaseTrx $model
     * @return void
     * @throws BindingResolutionException
     */
    public function setModel($model): void
    {
        // updated_atを自動設定
        $now = ClockUtility::now();
        $model->setAttribute('updated_at', $now);

        // ユニークキーを生成
        $uniqueKey = implode(':', array_map(fn($key) => $model->getAttribute($key), $this->getUniqueKeys()));
        
        // 新規モデル（IDがnull）の場合、一時的なユニークキーを生成
        if ($uniqueKey === '' || $uniqueKey === ':' || strpos($uniqueKey, ':') === 0 || strpos($uniqueKey, ':') === strlen($uniqueKey) - 1) {
            $uniqueKey = '_new_' . $this->newModelCounter++;
        }

        // 最初にsetModelが呼ばれた時のみ、変更前の状態を保存
        if (!isset($this->originalStateArray[$uniqueKey])) {
            $this->originalStateArray[$uniqueKey] = $model->getOriginal();
        }

        // CacheRecordTraitのキャッシュに保存
        if ($this->models === null) {
            $this->models = new CustomCollection();
        }
        $this->models->put($uniqueKey, $model);

        // 内部キューに溜め込む（同じキーは上書き = 最終状態を保持）
        $this->modelQueue[$uniqueKey] = $model;
    }

    /**
     * 削除対象モデルをセットし、削除キューに溜め込む
     *
     * @param _BaseTrx $model
     * @return void
     * @throws BindingResolutionException
     */
    protected function deleteModel($model): void
    {
        // 親クラスのdeleteModelを呼び出し
        parent::deleteModel($model);
    }

    /**
     * モデル保存後のフック（ログ記録など）
     * サブクラスでオーバーライドして、ログ記録処理を実装
     * 
     * @param _BaseTrx $model 保存されたモデル（最終状態）
     * @param array<string, mixed> $originalState 変更前の状態（初回setModel時の状態）
     * @return void
     */
    public function afterSave($model, array $originalState): void
    {
        // デフォルトでは何もしない
        // サブクラスでオーバーライドしてログ記録処理を実装
    }

    /**
     * 変更前の状態を取得
     *
     * @return array<string, array<string, mixed>> キー: ユニークキー, 値: オリジナル属性の配列
     */
    public function getOriginalStates(): array
    {
        return $this->originalStateArray;
    }

    /**
     * キューと変更前状態をクリア
     *
     * @return void
     */
    public function clearQueue(): void
    {
        parent::clearQueue();
        $this->originalStateArray = [];
        $this->newModelCounter = 0;
    }

    /**
     * is_delete=trueのレコードを削除キューに追加
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function deleteMarkedRecords(int $sysPlayerId): void
    {
        // is_delete=trueのレコードを取得
        $markedRecords = DB::connection($this->connection)
            ->table($this->getTableName())
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', true)
            ->get();

        // 各レコードを削除キューに追加
        foreach ($markedRecords as $record) {
            // Eloquentモデルのインスタンスに変換
            $model = new $this->modelClass();
            $model->forceFill((array) $record);
            $model->exists = true;
            
            // 削除キューに追加
            $this->deleteModel($model);
        }
    }

    /**
     * プレイヤーIDが設定されているかチェック
     * サブクラスまたはアプリケーションでオーバーライドして実装する
     *
     * @return bool
     * @throws \RuntimeException
     */
    protected static function hasSysPlayerId(): bool
    {
        throw new \RuntimeException('hasSysPlayerId() must be implemented by subclass or overridden by application');
    }

    /**
     * プレイヤーIDを取得（静的メソッド）
     * サブクラスまたはアプリケーションでオーバーライドして実装する
     *
     * @return int
     * @throws \RuntimeException
     */
    protected static function getSysPlayerId(): int
    {
        throw new \RuntimeException('getSysPlayerId() must be implemented by subclass or overridden by application');
    }

    /**
     * プレイヤーIDを設定（静的メソッド）
     * サブクラスまたはアプリケーションでオーバーライドして実装する
     *
     * @param int $sysPlayerId
     * @return void
     * @throws \RuntimeException
     */
    protected static function setSysPlayerId(int $sysPlayerId): void
    {
        throw new \RuntimeException('setSysPlayerId() must be implemented by subclass or overridden by application');
    }
}
