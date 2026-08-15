<?php

namespace Nexus\Core\Repositories\Trx;

use Nexus\Core\Models\Trx\_BaseTrx;
use Nexus\Core\Models\Trx\_BaseTrxInterface;
use Nexus\Core\Repositories\_BaseRepository;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use NexusPitr\Traits\LogsChanges;

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
    use LogsChanges;
    protected string $selectKey = 'sys_player_id';

    /**
     * データベース接続名（trx1, trx2など）
     * サブクラスでオーバーライド可能
     *
     * @var string
     */
    protected string $connection = 'trx1';

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
    protected function resolveCachedSysPlayerId(): int
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
        $sysPlayerId = $this->resolveCachedSysPlayerId();

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
    public function selectMapBySysPlayerId(int $sysPlayerId): CustomCollection
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
    public function selectBySysPlayerId(int $sysPlayerId): array
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
        // created_at / updated_at はテーブル定義の
        // DEFAULT CURRENT_TIMESTAMP / ON UPDATE CURRENT_TIMESTAMP に任せる

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
     * 物理削除（DELETE文で行を消す）
     *
     * PITRにはDELETEとして記録する。
     *
     * @param _BaseTrx $model
     * @return void
     * @throws BindingResolutionException
     */
    public function hardDeleteModel($model): void
    {
        // PITRログをキューに追加
        $sysPlayerId = $model->getAttribute('sys_player_id');
        $this->queueDeleteLog(
            sysPlayerId: $sysPlayerId,
            beforeData: $model->getOriginal(),
            primaryKey: $this->resolvePrimaryKeyValues($model)
        );

        parent::hardDeleteModel($model);
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
        // PITR変更ログを記録
        $this->recordPitrLog($model, $originalState);
    }

    /**
     * PITR変更ログをキューに追加
     * 
     * @param _BaseTrx $model 保存されたモデル（最終状態）
     * @param array<string, mixed> $originalState 変更前の状態
     * @return void
     */
    protected function recordPitrLog($model, array $originalState): void
    {
        $sysPlayerId = $model->getAttribute('sys_player_id');
        
        if (empty($originalState)) {
            // INSERT操作
            $this->queueInsertLog(
                sysPlayerId: $sysPlayerId,
                afterData: $model->getAttributes(),
                primaryKey: $this->resolvePrimaryKeyValues($model)
            );
        } else {
            // UPDATE操作（差分がある場合のみ）
            $afterData = $model->getAttributes();
            $diff = array_diff_assoc($afterData, $originalState);
            
            if (!empty($diff)) {
                $this->queueUpdateLog(
                    sysPlayerId: $sysPlayerId,
                    beforeData: $originalState,
                    afterData: $diff, // 差分のみ記録
                    primaryKey: $this->resolvePrimaryKeyValues($model)
                );
            }
        }
    }

    /**
     * 主キーの値を取得
     * 
     * @param _BaseTrx $model
     * @return array<string, mixed>
     */
    protected function resolvePrimaryKeyValues($model): array
    {
        $keyName = $model->getKeyName();
        
        if (is_array($keyName)) {
            // 複合主キー
            $pk = [];
            foreach ($keyName as $key) {
                $pk[$key] = $model->getAttribute($key);
            }
            return $pk;
        } else {
            // 単一主キー
            return [$keyName => $model->getKey()];
        }
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
        $this->clearPitrLogQueue(); // PITRログキューもクリア
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
