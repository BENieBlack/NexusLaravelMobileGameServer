# RedisUtility

## 概要

`RedisUtility`は、Redisキャッシュ操作を簡潔に行うためのユーティリティクラスです。毎回`Cache::store('redis')`を書く必要がなくなり、コードがより読みやすくなります。

**場所**: `api/app/Utilities/RedisUtility.php`

## 基本的な使い方

### キャッシュの保存と取得

```php
use App\Utilities\RedisUtility;

// 値を保存（TTL: 3600秒 = 1時間）
RedisUtility::put('user:123', $userData, 3600);

// 値を取得
$userData = RedisUtility::get('user:123');

// デフォルト値付きで取得
$userData = RedisUtility::get('user:123', ['default' => 'data']);

// 永続保存（TTLなし）
RedisUtility::forever('config:app', $config);
```

### 存在確認と削除

```php
// キーの存在確認
if (RedisUtility::has('user:123')) {
    // キーが存在する場合の処理
}

// キーを削除
RedisUtility::forget('user:123');

// 複数のキーを一括削除
RedisUtility::deleteMany(['user:123', 'user:456', 'user:789']);

// 取得と同時に削除
$value = RedisUtility::pull('temporary_key');
```

### remember パターン

```php
// キャッシュが存在すれば取得、なければクロージャを実行して保存
$expensiveData = RedisUtility::remember('expensive_calculation', 3600, function() {
    return performExpensiveCalculation();
});

// 永続キャッシュ版
$config = RedisUtility::rememberForever('app_config', function() {
    return loadConfiguration();
});
```

### カウンター操作

```php
// インクリメント
RedisUtility::increment('page_views');           // +1
RedisUtility::increment('page_views', 10);       // +10

// デクリメント
RedisUtility::decrement('stock_count');          // -1
RedisUtility::decrement('stock_count', 5);       // -5

// 初期値を設定してからインクリメント
RedisUtility::put('counter', 0, 3600);
$newValue = RedisUtility::increment('counter');  // 1
```

### 圧縮機能（大きなデータ用）

大きなデータをRedisに保存する際は、gzip圧縮を使用してメモリ使用量を削減できます。

```php
// 大きな配列データを圧縮して保存
$largeData = [
    'items' => array_fill(0, 1000, ['id' => 1, 'name' => 'Item']),
    'metadata' => ['large' => 'dataset'],
];

RedisUtility::putCompressed('large_dataset', $largeData, 3600);

// 解凍して取得
$data = RedisUtility::getCompressed('large_dataset');

// rememberパターンの圧縮版
$largeData = RedisUtility::rememberCompressed('large_calculation', 3600, function() {
    return generateLargeDataset();
});
```

### キーのプレフィックス管理

```php
// プレフィックスを付与
$key = RedisUtility::prefixKey('user', '123');        // "user:123"
$key = RedisUtility::prefixKey('session', 'abc123');  // "session:abc123"

// 使用例
RedisUtility::put(
    RedisUtility::prefixKey('player', $playerId),
    $playerData,
    3600
);
```

### 条件付き保存

```php
// キーが存在しない場合のみ保存
$added = RedisUtility::add('lock:process', 'locked', 60);

if ($added) {
    // ロック取得成功
    processTask();
    RedisUtility::forget('lock:process');
} else {
    // 既に他のプロセスが実行中
}
```

### テスト・デバッグ用

```php
// 全てのキャッシュをクリア（開発/テスト環境のみ使用）
RedisUtility::flush();
RedisUtility::clear();  // flushのエイリアス

// ⚠️ 注意: 本番環境では絶対に使用しないこと
```

## 実装例

### 冪等性キャッシュ（IdempotencyMiddleware）

```php
// Before: Cache::store('redis')を毎回書く必要があった
if (Cache::has($cacheKey)) {
    $compressed = Cache::get($cacheKey);
    // ...
}
Cache::put($cacheKey, $compressed, $ttl);

// After: RedisUtilityを使用
if (RedisUtility::has($cacheKey)) {
    $compressed = RedisUtility::get($cacheKey);
    // ...
}
RedisUtility::put($cacheKey, $compressed, $ttl);
```

### 課金の重複防止（IdempotencyService）

```php
use App\Utilities\RedisUtility;

class IdempotencyService
{
    private const CACHE_TTL = 86400;
    private const CACHE_KEY_PREFIX = 'billing:idempotency:';

    public function isDuplicate(string $uniqueRequestId): bool
    {
        $key = self::CACHE_KEY_PREFIX . $uniqueRequestId;
        return RedisUtility::has($key);
    }

    public function register(string $uniqueRequestId, array $result): void
    {
        $key = self::CACHE_KEY_PREFIX . $uniqueRequestId;
        RedisUtility::put($key, $result, self::CACHE_TTL);
    }

    public function getResult(string $uniqueRequestId): ?array
    {
        $key = self::CACHE_KEY_PREFIX . $uniqueRequestId;
        return RedisUtility::get($key);
    }
}
```

### プレイヤーセッションキャッシュ

```php
class PlayerSessionService
{
    private const TTL = 1800; // 30分

    public function cachePlayerSession(int $playerId, array $sessionData): void
    {
        $key = RedisUtility::prefixKey('player:session', (string)$playerId);
        RedisUtility::put($key, $sessionData, self::TTL);
    }

    public function getPlayerSession(int $playerId): ?array
    {
        $key = RedisUtility::prefixKey('player:session', (string)$playerId);
        return RedisUtility::get($key);
    }

    public function invalidateSession(int $playerId): void
    {
        $key = RedisUtility::prefixKey('player:session', (string)$playerId);
        RedisUtility::forget($key);
    }
}
```

### ランキングキャッシュ

```php
class RankingCache
{
    private const TTL = 300; // 5分

    public function getRanking(string $type): array
    {
        return RedisUtility::rememberCompressed(
            "ranking:{$type}",
            self::TTL,
            function() use ($type) {
                return $this->fetchRankingFromDatabase($type);
            }
        );
    }

    public function clearRanking(string $type): void
    {
        RedisUtility::forget("ranking:{$type}");
    }
}
```

## 提供メソッド一覧

### 基本操作
- `put(string $key, mixed $value, int|null $ttl)`: 値を保存
- `get(string $key, mixed $default = null)`: 値を取得
- `forever(string $key, mixed $value)`: 永続保存
- `forget(string $key)`: 削除
- `has(string $key)`: 存在確認
- `pull(string $key, mixed $default = null)`: 取得して削除

### 高度な操作
- `add(string $key, mixed $value, int|null $ttl)`: 存在しない場合のみ保存
- `increment(string $key, int $value = 1)`: インクリメント
- `decrement(string $key, int $value = 1)`: デクリメント
- `remember(string $key, int|null $ttl, Closure $callback)`: キャッシュまたは生成
- `rememberForever(string $key, Closure $callback)`: 永続キャッシュまたは生成

### 圧縮機能
- `putCompressed(string $key, mixed $value, int|null $ttl)`: 圧縮して保存
- `getCompressed(string $key, mixed $default = null)`: 解凍して取得
- `rememberCompressed(string $key, int|null $ttl, Closure $callback)`: 圧縮版remember

### ユーティリティ
- `prefixKey(string $prefix, string $key)`: プレフィックス付与
- `deleteMany(array $keys)`: 複数削除
- `flush()` / `clear()`: 全削除（開発/テスト用）

### 実験的機能（使用注意）
- `ttl(string $key)`: TTL取得（Laravelのプレフィックスの影響で動作しない可能性あり）
- `expire(string $key, int $ttl)`: TTL更新（Laravelのプレフィックスの影響で動作しない可能性あり）
- `keys(string $pattern)`: パターンマッチ（大量のキーがある場合は重い）

## ベストプラクティス

### 1. キー命名規則

```php
// ✅ Good: コロンで階層を表現
RedisUtility::put('player:123:inventory', $data, 3600);
RedisUtility::put('session:abc123:auth', $auth, 1800);
RedisUtility::put('ranking:weekly:top100', $ranking, 300);

// ❌ Bad: フラットな命名
RedisUtility::put('player_123_inventory', $data, 3600);
```

### 2. TTLの設定

```php
// ✅ Good: 必ずTTLを設定する
RedisUtility::put('temporary_data', $data, 3600);

// ❌ Bad: TTLなし（メモリリークの原因）
RedisUtility::forever('temporary_data', $data);
```

### 3. 圧縮の使用基準

```php
// ✅ Good: 大きなデータ（1KB以上）は圧縮
RedisUtility::putCompressed('large_dataset', $largeArray, 3600);

// ✅ Good: 小さなデータは圧縮しない（オーバーヘッドが大きい）
RedisUtility::put('small_flag', true, 3600);
```

### 4. エラーハンドリング

```php
// ✅ Good: デフォルト値を使用
$config = RedisUtility::get('config', ['default' => 'value']);

// ✅ Good: 存在確認してから処理
if (RedisUtility::has('important_data')) {
    $data = RedisUtility::get('important_data');
    process($data);
}
```

## テスト

`RedisUtility`には包括的なユニットテストが用意されています。

**テストファイル**: `api/tests/Unit/Utilities/RedisUtilityTest.php`

```bash
# RedisUtilityのテストを実行
./vendor/bin/pest tests/Unit/Utilities/RedisUtilityTest.php

# 全テストを実行
./vendor/bin/pest
```

## 注意事項

1. **Redis接続が必要**: このユーティリティはRedisストアを使用するため、Redis接続が必要です
2. **本番環境でのflush禁止**: `flush()`や`clear()`は開発/テスト環境でのみ使用してください
3. **TTLの設定を忘れずに**: メモリリークを防ぐため、必ずTTLを設定してください
4. **圧縮のオーバーヘッド**: 小さなデータ（<1KB）を圧縮すると逆にサイズが増える可能性があります
5. **キーの命名規則**: プロジェクト全体で統一されたキー命名規則を使用してください

## 関連ファイル

- 実装: `api/app/Utilities/RedisUtility.php`
- テスト: `api/tests/Unit/Utilities/RedisUtilityTest.php`
- 使用例: `api/app/Http/Middleware/IdempotencyMiddleware.php`
- 使用例: `api/app/Domain/Billing/Services/IdempotencyService.php`
