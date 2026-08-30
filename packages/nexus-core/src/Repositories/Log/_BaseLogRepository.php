<?php

namespace Nexus\Core\Repositories\Log;

use Illuminate\Contracts\Container\BindingResolutionException;
use Nexus\Core\Models\Log\_BaseLog;
use Nexus\Core\Repositories\_BaseRepository;
use Nexus\Core\Support\CustomCollection;

/**
 * _BaseLogRepository
 *
 * ログRepositoryの基底クラス
 * ログテーブルはINSERT ONLYのため、setModelメソッドでINSERTのみ実行
 * プレイヤーIDはApiSessionから自動的に取得される
 *
 * @template T of _BaseLog
 *
 * @extends _BaseRepository<int, T>
 *
 * @implements _BaseLogRepositoryInterface<T>
 */
abstract class _BaseLogRepository extends _BaseRepository implements _BaseLogRepositoryInterface
{
    /**
     * データベース接続名（通常は 'log'）
     */
    protected string $connection = 'log1';

    /**
     * 使用するデータベース接続名を返す
     *
     * setConnection() で明示されていればそれを、
     * 無ければログイン中プレイヤーの割り当てシャードに対応するLogDBを使う。
     */
    public function getConnection(): string
    {
        if ($this->connectionExplicitlySet) {
            return $this->connection;
        }

        return static::resolveShardConnection() ?? $this->connection;
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
     * 課金ログかどうか
     */
    /**
     * 課金関連のログか
     *
     * 注意: この値はサブクラスが宣言するだけで、現状どこからも読まれていない。
     * QueryManagerの課金ログ枠へ入れるには setModel($model, true) のように
     * 明示的に渡す必要がある。
     */
    protected bool $isPurchaseLog = false;

    /**
     * 検索キー（通常は 'sys_player_id'）
     *
     * @var string
     */
    /**
     * @var list<string> 自分の行を絞り込むカラム名。Repositoryで明示する場合のみ設定する
     *
     * 空ならModelの宣言に従う。
     */
    protected array $selectKeys = [];

    /**
     * キャッシュがどのプレイヤーのものかを保持する
     * 別プレイヤーで問い合わせられたらキャッシュを破棄して取り直す
     */
    private ?int $cachedForSysPlayerId = null;

    /**
     * プレイヤーIDを取得（内部用）
     * PlayerSessionResolverから自動的に取得し、インスタンス内でキャッシュする
     *
     * パフォーマンス最適化:
     * - 初回呼び出し時にPlayerSessionResolverから取得してキャッシュ
     * - 2回目以降はキャッシュされた値を返す（app()の呼び出しを回避）
     *
     * @return int プレイヤーID
     *
     * @throws \RuntimeException プレイヤーIDが取得できない場合
     */
    protected function resolveCachedSysPlayerId(): int
    {
        // インスタンスに固定しない。
        // Repositoryはリクエスト単位で共有されるため、途中でプレイヤーが
        // 切り替わる経路（selectBySysPlayerId等）で古いIDを返してしまう
        if (static::hasSysPlayerId()) {
            return static::getSysPlayerId();
        }

        // PlayerSessionResolverが未設定の場合はエラー
        throw new \RuntimeException(
            'Player ID is not available. Make sure authentication middleware is applied.'
        );
    }

    /**
     * データベースまたはメモリキャッシュからログデータを取得
     * キャッシュがなければsys_player_idで検索してキャッシュに保存
     * キャッシュがあればキャッシュを返す
     * プレイヤーIDはPlayerSessionResolverから自動的に取得される
     *
     * @return CustomCollection<int, T>
     *
     * @throws \RuntimeException プレイヤーIDが取得できない場合
     */
    /**
     * {@inheritDoc}
     *
     * どのプレイヤー向けのキャッシュだったかも忘れる
     */
    public function forgetCachedModels(): void
    {
        parent::forgetCachedModels();
        $this->cachedForSysPlayerId = null;
    }

    public function queryOrMemory(): CustomCollection
    {
        // プレイヤーIDを先に解決する（キャッシュが誰のものかの判定に使う）
        $sysPlayerId = $this->resolveCachedSysPlayerId();

        // 同じプレイヤーのキャッシュがあればそれを返す
        // 0件だった場合もキャッシュとして扱う（毎回問い合わせない）
        if ($this->models !== null && $this->cachedForSysPlayerId === $sysPlayerId) {
            return $this->models;
        }

        // キャッシュが空の場合、データベースから取得
        /** @var T $instance */
        $instance = new $this->modelClass;

        // sys_player_idで検索してIDでkeyByしてキャッシュに保存
        $records = $instance::on($this->getConnection())
            ->where($this->getSelectKeys()[0], $sysPlayerId)
            ->get()
            ->keyBy('id');

        /** @var CustomCollection<int, T> $cached ログIDはintのAUTO_INCREMENT */
        $cached = new CustomCollection($records->all());
        $this->models = $cached;
        $this->cachedForSysPlayerId = $sysPlayerId;

        return $this->models;
    }

    /**
     * IDでログレコードを取得
     * メモリキャッシュから取得、なければqueryOrMemoryでロードしてから取得
     *
     * @param  int  $logRecordId  ログID
     * @return T|null ログレコード（見つからない場合はnull）
     */
    public function selectById(int $logRecordId)
    {
        // メモリキャッシュにあればそこから取得
        if ($this->models !== null) {
            return $this->models->get($logRecordId);
        }

        // キャッシュがない場合、queryOrMemoryでロードしてから取得
        return $this->queryOrMemory()->get($logRecordId);
    }

    /**
     * ログモデルをセットし、内部キューに溜め込む
     * ログは常にINSERTのみ（配列に追加、上書きしない）
     *
     * @param  T  $model
     * @param  bool|null  $isPurchase  課金関連のログかどうか（nullの場合はプロパティの値を使用）
     *
     * @throws BindingResolutionException
     */
    public function setModel($model, ?bool $isPurchase = null): void
    {
        // 内部キューに溜め込む（配列に追加、上書きしない）
        $this->modelQueue[] = $model;
    }

    /**
     * プレイヤーIDが設定されているかチェック
     * サブクラスまたはアプリケーションでオーバーライドして実装する
     *
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
     * @throws \RuntimeException
     */
    protected static function setSysPlayerId(int $sysPlayerId): void
    {
        throw new \RuntimeException('setSysPlayerId() must be implemented by subclass or overridden by application');
    }

    /**
     * 絞り込みキーを取得
     *
     * Repositoryでの明示があればそれを優先し、無ければModelに聞く。
     * どちらも未設定なら sys_player_id を使う。
     *
     * @return list<string>
     */
    protected function getSelectKeys(): array
    {
        if ($this->selectKeys !== []) {
            return $this->selectKeys;
        }

        $modelKeys = $this->getModelInstance()->getSelectKeys();

        return $modelKeys !== [] ? $modelKeys : ['sys_player_id'];
    }
}
