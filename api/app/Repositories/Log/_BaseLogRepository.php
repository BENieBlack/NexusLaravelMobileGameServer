<?php

namespace App\Repositories\Log;

use App\Models\Log\_BaseLog;
use App\Repositories\_BaseRepository;
use App\Repositories\LogQueryManager as QueryLogManager;
use App\Utilities\ApiSession;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * _BaseLogRepository
 *
 * ログRepositoryの基底クラス
 * ログテーブルはINSERT ONLYのため、setModelメソッドでINSERTのみ実行
 * プレイヤーIDはApiSessionから自動的に取得される
 */
abstract class _BaseLogRepository extends _BaseRepository implements _BaseLogRepositoryInterface
{
    /**
     * データベース接続名（通常は 'log'）
     *
     * @var string
     */
    protected string $connection = 'log';

    /**
     * 課金ログかどうか
     *
     * @var bool
     */
    protected bool $isPurchaseLog = false;

    /**
     * 検索キー（通常は 'sys_player_id'）
     *
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * キャッシュされたプレイヤーID（パフォーマンス最適化）
     * ApiSessionから取得した値を保持し、毎回app()を呼ばないようにする
     *
     * @var int|null
     */
    private ?int $cachedSysPlayerId = null;

    /**
     * プレイヤーIDを取得（内部用）
     * ApiSessionから自動的に取得し、インスタンス内でキャッシュする
     * 
     * パフォーマンス最適化:
     * - 初回呼び出し時にApiSessionから取得してキャッシュ
     * - 2回目以降はキャッシュされた値を返す（app()の呼び出しを回避）
     *
     * @return int プレイヤーID
     * @throws \RuntimeException プレイヤーIDが取得できない場合
     */
    protected function getSysPlayerId(): int
    {
        // キャッシュがあればそれを返す（高速パス）
        if ($this->cachedSysPlayerId !== null) {
            return $this->cachedSysPlayerId;
        }

        // ApiSessionから取得してキャッシュ
        if (ApiSession::hasSysPlayerId()) {
            $this->cachedSysPlayerId = ApiSession::getSysPlayerId();
            return $this->cachedSysPlayerId;
        }

        // ApiSessionが未設定の場合はエラー
        throw new \RuntimeException(
            'Player ID is not available. Make sure authentication middleware is applied.'
        );
    }

    /**
     * データベースまたはメモリキャッシュからログデータを取得
     * キャッシュがなければsys_player_idで検索してキャッシュに保存
     * キャッシュがあればキャッシュを返す
     * プレイヤーIDはApiSessionから自動的に取得される
     *
     * @return Collection<int, _BaseLog>
     * @throws \RuntimeException プレイヤーIDが取得できない場合
     */
    public function queryOrMemory(): Collection
    {
        // メモリキャッシュにデータがあればそれを返す
        if ($this->models !== null && $this->models->isNotEmpty()) {
            return $this->models;
        }

        // プレイヤーIDを取得（ApiSession優先、なければ$sysPlayerIdフィールド）
        $sysPlayerId = $this->getSysPlayerId();

        // キャッシュが空の場合、データベースから取得
        /** @var _BaseLog $instance */
        $instance = new $this->modelClass();

        // sys_player_idで検索してIDでkeyByしてキャッシュに保存
        $this->models = $instance::on($this->connection)
            ->where($this->selectKey, $sysPlayerId)
            ->get()
            ->keyBy('id');

        return $this->models;
    }

    /**
     * IDでログレコードを取得
     * メモリキャッシュから取得、なければqueryOrMemoryでロードしてから取得
     *
     * @param int $id ログID
     * @return _BaseLog|null ログレコード（見つからない場合はnull）
     */
    public function getById(int $id): ?_BaseLog
    {
        // メモリキャッシュにあればそこから取得
        if ($this->models !== null) {
            return $this->models->get($id);
        }

        // キャッシュがない場合、queryOrMemoryでロードしてから取得
        $this->queryOrMemory();
        return $this->models?->get($id);
    }

    /**
     * ログモデルをセットし、内部キューに溜め込む
     * ログは常にINSERTのみ（配列に追加、上書きしない）
     *
     * @param Model $model
     * @param bool|null $isPurchase 課金関連のログかどうか（nullの場合はプロパティの値を使用）
     * @return void
     * @throws BindingResolutionException
     */
    public function setModel($model, ?bool $isPurchase = null): void
    {
        // 内部キューに溜め込む（配列に追加、上書きしない）
        $this->modelQueue[] = $model;

        // QueryLogManagerに自身を登録（初回のみ）
        if (!$this->registeredToManager) {
            $queryManager = app()->make(QueryLogManager::class);
            $queryManager->registerRepository($this, $isPurchase ?? $this->isPurchaseLog);
            $this->registeredToManager = true;
        }
    }
}
