<?php

namespace App\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusSecurity\Contracts\PlayerSessionInterface;
use NexusUnitOfWork\Contracts\PlayerSessionResolverInterface;

/**
 * ApiSession
 *
 * APIコール時に確定したリクエストコンテキスト情報を保持する汎用クラス
 * リクエストスコープで管理され、Middlewareで初期化される
 *
 * 管理している情報:
 * - sysPlayerId: 認証されたプレイヤーID
 * - now: リクエスト開始時の固定時刻（ClockUtility::now()）
 * - language: Accept-Languageから解決した言語コード
 *
 * 使用例:
 * - Middleware: ApiSession::setSysPlayerId($sysPlayerId)
 * - Repository/Service/UseCase:
 *   - $sysPlayerId = ApiSession::getSysPlayerId()
 *   - $now = ApiSession::getNow()
 * - インスタンス操作: app(ApiSession::class)->getPlayerId()
 */
class ApiSession implements PlayerSessionInterface, PlayerSessionResolverInterface
{
    /**
     * プレイヤーID
     */
    private ?int $sysPlayerId = null;

    /**
     * リクエスト開始時刻（固定値）
     */
    private ?CarbonImmutable $now = null;

    /**
     * トランザクションDB接続名のキャッシュ
     */
    private ?string $connectionName = null;

    /**
     * リクエストの言語コード（Accept-Languageから解決した結果）
     */
    private ?string $language = null;

    /**
     * コンストラクタ
     *
     * @param  int|null  $sysPlayerId  プレイヤーID（オプション）
     * @param  CarbonImmutable|null  $now  リクエスト開始時刻（オプション）
     */
    public function __construct(?int $sysPlayerId = null, ?CarbonImmutable $now = null)
    {
        $this->sysPlayerId = $sysPlayerId;
        $this->now = $now;
    }

    /**
     * プレイヤーIDを設定（インスタンスメソッド）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function setPlayerIdInstance(int $sysPlayerId): void
    {
        $this->sysPlayerId = $sysPlayerId;
    }

    /**
     * プレイヤーIDを取得（インスタンスメソッド）
     *
     * @return int プレイヤーID
     *
     * @throws \RuntimeException プレイヤーIDが設定されていない場合
     */
    public function getPlayerId(): int
    {
        if ($this->sysPlayerId === null) {
            throw new \RuntimeException(
                'Player ID is not set in ApiSession. Make sure authentication middleware is applied.'
            );
        }

        return $this->sysPlayerId;
    }

    /**
     * プレイヤーIDが設定されているか確認（インスタンスメソッド）
     */
    public function hasPlayerId(): bool
    {
        return $this->sysPlayerId !== null;
    }

    /**
     * リクエスト開始時刻を設定（インスタンスメソッド）
     *
     * @param  CarbonImmutable  $now  リクエスト開始時刻
     */
    public function setNow(CarbonImmutable $now): void
    {
        $this->now = $now;
    }

    /**
     * リクエスト開始時刻を取得（インスタンスメソッド）
     *
     * @return CarbonImmutable リクエスト開始時刻
     *
     * @throws \RuntimeException リクエスト開始時刻が設定されていない場合
     */
    public function getNowValue(): CarbonImmutable
    {
        if ($this->now === null) {
            throw new \RuntimeException(
                'Request time is not set in ApiSession. Make sure authentication middleware is applied.'
            );
        }

        return $this->now;
    }

    /**
     * リクエスト開始時刻が設定されているか確認（インスタンスメソッド）
     */
    public function hasNowValue(): bool
    {
        return $this->now !== null;
    }

    /**
     * セッション情報をクリア（主にテスト用）
     */
    public function clear(): void
    {
        $this->sysPlayerId = null;
        $this->now = null;
        $this->connectionName = null;
        $this->language = null;
    }

    /**
     * プレイヤーIDからトランザクションDB接続名を取得（インスタンスメソッド）
     *
     * P1-3: Redisキャッシュを使用してシャード解決のDBクエリを削減
     * キャッシュ階層:
     * 1. インスタンスキャッシュ（リクエストスコープ）
     * 2. Redisキャッシュ（TTL: 1時間）
     * 3. DBクエリ（キャッシュミス時のみ）
     *
     * @return string 接続名（trx1 または trx2）
     *
     * @throws \RuntimeException プレイヤーIDが設定されていない場合、またはシャーディング情報が見つからない場合
     */
    public function resolveConnectionNameValue(): string
    {
        // 1. インスタンスキャッシュがあれば返す（リクエストスコープ）
        if ($this->connectionName !== null) {
            return $this->connectionName;
        }

        $sysPlayerId = $this->getPlayerId();
        $cacheKey = "shard:player:{$sysPlayerId}";

        // 2. Redisキャッシュから取得を試みる
        $cachedConnection = \Cache::get($cacheKey);
        if ($cachedConnection !== null) {
            $this->connectionName = $cachedConnection;

            return $this->connectionName;
        }

        // 3. キャッシュミス: DBから取得
        // プレイヤーのシャーディングノード情報を取得
        $shardingNodePlayer = DB::connection('sys')
            ->table('sys_sharding_node_player')
            ->where('sys_player_id', $sysPlayerId)
            ->first();

        if ($shardingNodePlayer === null) {
            throw new \RuntimeException(
                "Sharding node assignment not found for player ID: {$sysPlayerId}. ".
                'Player may not be assigned to a shard.'
            );
        }

        // ノード情報を取得
        $shardingNode = DB::connection('sys')
            ->table('sys_sharding_node')
            ->where('id', $shardingNodePlayer->sys_sharding_node_id)
            ->first();

        if ($shardingNode === null) {
            throw new \RuntimeException(
                "Sharding node not found for node ID: {$shardingNodePlayer->sys_sharding_node_id}"
            );
        }

        // node_noに基づいて接続名を決定（node_no: 1 → trx1, node_no: 2 → trx2）
        $this->connectionName = 'trx'.$shardingNode->node_no;

        // Redisにキャッシュ（TTL: 1時間、シャード割り当ては頻繁に変わらない想定）
        \Cache::put($cacheKey, $this->connectionName, 3600);

        return $this->connectionName;
    }

    /**
     * 静的ヘルパー: プレイヤーIDとリクエスト開始時刻を設定
     * Middlewareから簡単に呼び出せるヘルパーメソッド
     * リクエスト開始時刻はClockUtility::now()で自動的に設定される
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public static function setSysPlayerId(int $sysPlayerId): void
    {
        $now = ClockUtility::now();

        if (! app()->bound(self::class)) {
            app()->instance(self::class, new self($sysPlayerId, $now));
        } else {
            $instance = app(self::class);
            $instance->setPlayerIdInstance($sysPlayerId);
            $instance->setNow($now);
        }
    }

    /**
     * 静的ヘルパー: プレイヤーIDを設定（PlayerSessionInterface実装）
     * セキュリティミドルウェアパッケージからの呼び出し用
     *
     * @param  int  $playerId  プレイヤーID
     */
    public static function setPlayerId(int $playerId): void
    {
        self::setSysPlayerId($playerId);
    }

    /**
     * 静的ヘルパー: プレイヤーIDを取得
     * どこからでも簡単に呼び出せるヘルパーメソッド
     *
     * @return int プレイヤーID
     *
     * @throws \RuntimeException プレイヤーIDが設定されていない場合
     */
    public static function getSysPlayerId(): int
    {
        return app(self::class)->getPlayerId();
    }

    /**
     * 静的ヘルパー: プレイヤーIDが設定されているか確認
     */
    public static function hasSysPlayerId(): bool
    {
        if (! app()->bound(self::class)) {
            return false;
        }

        return app(self::class)->hasPlayerId();
    }

    /**
     * 静的ヘルパー: リクエスト開始時刻を取得
     * どこからでも簡単に呼び出せるヘルパーメソッド
     *
     * @return CarbonImmutable リクエスト開始時刻
     *
     * @throws \RuntimeException リクエスト開始時刻が設定されていない場合
     */
    public static function getNow(): CarbonImmutable
    {
        return app(self::class)->getNowValue();
    }

    /**
     * 静的ヘルパー: リクエスト開始時刻が設定されているか確認
     */
    public static function hasNow(): bool
    {
        if (! app()->bound(self::class)) {
            return false;
        }

        return app(self::class)->hasNowValue();
    }

    /**
     * 言語コードを設定（インスタンスメソッド）
     *
     * @param  string  $language  言語コード（config('language.supported')のいずれか）
     */
    public function setLanguageInstance(string $language): void
    {
        $this->language = $language;
    }

    /**
     * 言語コードを取得（インスタンスメソッド）
     *
     * 未設定なら既定の言語を返す（バッチやCLIなどHTTP経由でない実行を想定）
     */
    public function getLanguageValue(): string
    {
        return $this->language ?? (string) config('language.default');
    }

    /**
     * 静的ヘルパー: 言語コードを設定
     *
     * @param  string  $language  言語コード
     */
    public static function setLanguage(string $language): void
    {
        if (! app()->bound(self::class)) {
            app()->instance(self::class, new self);
        }

        app(self::class)->setLanguageInstance($language);
    }

    /**
     * 静的ヘルパー: 言語コードを取得
     *
     * @return string 言語コード（未設定ならconfig('language.default')）
     */
    public static function getLanguage(): string
    {
        if (! app()->bound(self::class)) {
            return (string) config('language.default');
        }

        return app(self::class)->getLanguageValue();
    }

    /**
     * 静的ヘルパー: テスト用にApiSessionをクリア
     */
    public static function clearForTest(): void
    {
        if (app()->bound(self::class)) {
            app(self::class)->clear();
        }
    }

    /**
     * 静的ヘルパー: トランザクションDB接続名を取得
     * プレイヤーIDに基づいてシャーディングされたDB接続名を返す
     *
     * @param  string  $baseConnection  ベース接続名（デフォルト: 'trx'）
     * @return string 接続名（trx1 または trx2）
     *
     * @throws \RuntimeException プレイヤーIDが設定されていない場合、またはシャーディング情報が見つからない場合
     */
    public static function resolveConnectionName(string $baseConnection = 'trx'): string
    {
        return app(self::class)->resolveConnectionNameValue();
    }
}
