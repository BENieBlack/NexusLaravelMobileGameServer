<?php

namespace Nexus\Core\Utilities;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;

/**
 * RedisUtility
 * 
 * Redisキャッシュ操作のユーティリティクラス
 * 毎回store('redis')を書かずにRedis操作ができる
 * 
 * 使用例:
 * ```php
 * // 基本操作
 * RedisUtility::put('key', 'value', 3600);
 * $value = RedisUtility::get('key');
 * RedisUtility::forget('key');
 * 
 * // 存在確認
 * if (RedisUtility::has('key')) {
 *     // キーが存在する場合の処理
 * }
 * 
 * // 全削除（開発/テスト用）
 * RedisUtility::flush();
 * 
 * // TTL付きキャッシュ
 * RedisUtility::remember('expensive_key', 3600, function() {
 *     return expensiveCalculation();
 * });
 * 
 * // 圧縮を使用したキャッシュ（大きなデータ用）
 * RedisUtility::putCompressed('large_data', $largeArray, 3600);
 * $data = RedisUtility::fetchCompressed('large_data');
 * ```
 */
class RedisUtility
{
    /**
     * gzip圧縮レベル（1-9: 高いほど圧縮率が高いが時間がかかる）
     */
    private const COMPRESSION_LEVEL = 6;

    /**
     * Redisストアのインスタンスを取得
     *
     * @return Repository
     */
    private static function store(): Repository
    {
        return Cache::store('redis');
    }

    /**
     * キャッシュに値を保存
     *
     * @param string $key キャッシュキー
     * @param mixed $value 保存する値
     * @param int|\DateTimeInterface|\DateInterval|null $ttl 有効期限（秒）
     * @return bool
     */
    public static function put(string $key, mixed $value, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool
    {
        return self::store()->put($key, $value, $ttl);
    }

    /**
     * キャッシュから値を取得
     *
     * @param string $key キャッシュキー
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::store()->get($key, $default);
    }

    /**
     * キャッシュに値を永久保存
     *
     * @param string $key キャッシュキー
     * @param mixed $value 保存する値
     * @return bool
     */
    public static function forever(string $key, mixed $value): bool
    {
        return self::store()->forever($key, $value);
    }

    /**
     * キャッシュから値を削除
     *
     * @param string $key キャッシュキー
     * @return bool
     */
    public static function forget(string $key): bool
    {
        return self::store()->forget($key);
    }

    /**
     * キャッシュにキーが存在するか確認
     *
     * @param string $key キャッシュキー
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::store()->has($key);
    }

    /**
     * キャッシュにキーが存在しない場合のみ保存
     *
     * @param string $key キャッシュキー
     * @param mixed $value 保存する値
     * @param int|\DateTimeInterface|\DateInterval|null $ttl 有効期限（秒）
     * @return bool
     */
    public static function add(string $key, mixed $value, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool
    {
        return self::store()->add($key, $value, $ttl);
    }

    /**
     * 値を増加させる
     *
     * @param string $key キャッシュキー
     * @param int $value 増加量（デフォルト: 1）
     * @return int|bool
     */
    public static function increment(string $key, int $value = 1): int|bool
    {
        return self::store()->increment($key, $value);
    }

    /**
     * 値を減少させる
     *
     * @param string $key キャッシュキー
     * @param int $value 減少量（デフォルト: 1）
     * @return int|bool
     */
    public static function decrement(string $key, int $value = 1): int|bool
    {
        return self::store()->decrement($key, $value);
    }

    /**
     * キャッシュが存在すれば取得、なければクロージャを実行して保存
     *
     * @param string $key キャッシュキー
     * @param int|\DateTimeInterface|\DateInterval|null $ttl 有効期限（秒）
     * @param \Closure $callback キャッシュが存在しない場合に実行するクロージャ
     * @return mixed
     */
    public static function remember(string $key, int|\DateTimeInterface|\DateInterval|null $ttl, \Closure $callback): mixed
    {
        return self::store()->remember($key, $ttl, $callback);
    }

    /**
     * キャッシュが存在すれば取得、なければクロージャを実行して永久保存
     *
     * @param string $key キャッシュキー
     * @param \Closure $callback キャッシュが存在しない場合に実行するクロージャ
     * @return mixed
     */
    public static function rememberForever(string $key, \Closure $callback): mixed
    {
        return self::store()->rememberForever($key, $callback);
    }

    /**
     * キャッシュから取得して削除
     *
     * @param string $key キャッシュキー
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::store()->pull($key, $default);
    }

    /**
     * 全てのキャッシュをクリア
     * 
     * 注意: 本番環境では使用しないこと
     * 主にテストやデバッグ用
     *
     * @return bool
     */
    public static function flush(): bool
    {
        return self::store()->flush();
    }

    /**
     * 全てのキャッシュをクリア（flushのエイリアス）
     * 
     * 注意: 本番環境では使用しないこと
     * 主にテストやデバッグ用
     *
     * @return bool
     */
    public static function clear(): bool
    {
        return self::flush();
    }

    /**
     * 複数のキーを一括取得
     *
     * @param array<string> $keys キャッシュキーの配列
     * @return array<string, mixed>
     */
    public static function many(array $keys): array
    {
        return self::store()->many($keys);
    }

    /**
     * 複数のキーを一括保存
     *
     * @param array<string, mixed> $values キーと値の連想配列
     * @param int|\DateTimeInterface|\DateInterval|null $ttl 有効期限（秒）
     * @return bool
     */
    public static function putMany(array $values, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool
    {
        return self::store()->putMany($values, $ttl);
    }

    /**
     * gzip圧縮して値を保存
     * 
     * 大きなデータをキャッシュする場合に使用
     * メモリ使用量を削減できる
     *
     * @param string $key キャッシュキー
     * @param mixed $value 保存する値
     * @param int|\DateTimeInterface|\DateInterval|null $ttl 有効期限（秒）
     * @return bool
     */
    public static function putCompressed(string $key, mixed $value, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool
    {
        $jsonData = json_encode($value);
        $compressed = gzencode($jsonData, self::COMPRESSION_LEVEL);
        
        return self::put($key, $compressed, $ttl);
    }

    /**
     * gzip圧縮された値を取得して解凍
     *
     * @param string $key キャッシュキー
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public static function fetchCompressed(string $key, mixed $default = null): mixed
    {
        $compressed = self::get($key);
        
        if ($compressed === null) {
            return $default;
        }
        
        $jsonData = gzdecode($compressed);
        
        if ($jsonData === false) {
            return $default;
        }
        
        return json_decode($jsonData, true);
    }

    /**
     * 圧縮キャッシュが存在すれば取得、なければクロージャを実行して圧縮保存
     *
     * @param string $key キャッシュキー
     * @param int|\DateTimeInterface|\DateInterval|null $ttl 有効期限（秒）
     * @param \Closure $callback キャッシュが存在しない場合に実行するクロージャ
     * @return mixed
     */
    public static function rememberCompressed(string $key, int|\DateTimeInterface|\DateInterval|null $ttl, \Closure $callback): mixed
    {
        if (self::has($key)) {
            return self::fetchCompressed($key);
        }
        
        $value = $callback();
        self::putCompressed($key, $value, $ttl);
        
        return $value;
    }

    /**
     * キーにプレフィックスを付与
     * 
     * 名前空間を分けたい場合に使用
     *
     * @param string $prefix プレフィックス
     * @param string $key キー
     * @return string
     */
    public static function prefixKey(string $prefix, string $key): string
    {
        return $prefix . ':' . $key;
    }

    /**
     * 複数のキーを一括削除
     *
     * @param array<string> $keys キャッシュキーの配列
     * @return bool
     */
    public static function deleteMany(array $keys): bool
    {
        foreach ($keys as $key) {
            self::forget($key);
        }
        
        return true;
    }

    /**
     * TTLを取得（秒）
     * 
     * Redisのネイティブ機能を使用
     * 注意: Laravelのキャッシュプレフィックスの影響で正しく動作しない可能性があります
     * 
     * @param string $key キャッシュキー
     * @return int|null TTL（秒）。キーが存在しない場合null、永続の場合-1
     */
    public static function ttl(string $key): ?int
    {
        $redis = self::store()->getStore();
        
        // PhpRedisまたはPredisの場合
        if (method_exists($redis, 'getRedis')) {
            $ttl = $redis->getRedis()->ttl($key);
            
            // -2: キーが存在しない
            if ($ttl === -2) {
                return null;
            }
            
            // -1: 永続キー
            if ($ttl === -1) {
                return -1;
            }
            
            return $ttl;
        }
        
        return null;
    }

    /**
     * キーの有効期限を更新
     * 
     * 注意: Laravelのキャッシュプレフィックスの影響で正しく動作しない可能性があります
     *
     * @param string $key キャッシュキー
     * @param int $ttl 新しい有効期限（秒）
     * @return bool
     */
    public static function expire(string $key, int $ttl): bool
    {
        $redis = self::store()->getStore();
        
        // PhpRedisまたはPredisの場合
        if (method_exists($redis, 'getRedis')) {
            return (bool) $redis->getRedis()->expire($key, $ttl);
        }
        
        return false;
    }

    /**
     * パターンに一致するキーを検索
     * 
     * 注意: 本番環境では大量のキーがある場合にパフォーマンスに影響する可能性がある
     * 主に開発/テスト用
     *
     * @param string $pattern パターン（例: "user:*", "session:*"）
     * @return array<string>
     */
    public static function keys(string $pattern): array
    {
        $redis = self::store()->getStore();
        
        // PhpRedisまたはPredisの場合
        if (method_exists($redis, 'getRedis')) {
            return $redis->getRedis()->keys($pattern);
        }
        
        return [];
    }
}
