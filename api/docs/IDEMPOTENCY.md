# 冪等性機能実装ガイド

## 概要

`X-Unique-Request-Identifier`ヘッダーを使用したリクエストの冪等性保証機能が実装されています。

## 主な機能

- **重複リクエストの検知**: 同じリクエストIDによる重複実行を防止
- **レスポンスキャッシュ**: 成功したレスポンスをRedisにキャッシュ
- **gzip圧縮**: レスポンスをgzip圧縮してRedisのメモリ使用量を削減（圧縮率: 最大98%）
- **リトライセーフ**: ネットワークエラー時のリトライを安全に実行

## 技術仕様

### キャッシュ

- **ストレージ**: Redis
- **キーフォーマット**: `idempotency:{sys_player_id}:{unique_request_id}:{api_path}`
- **TTL**: 24時間（86400秒）- 設定変更可能
- **対象**: 成功レスポンス（HTTP 2xx）のみ
- **圧縮**: gzip圧縮レベル6（最適なバランス）
- **圧縮効果**:
  - 小サイズレスポンス（~100 bytes）: 圧縮率 2%
  - 中サイズレスポンス（~2 KB）: 圧縮率 89%
  - 大サイズレスポンス（~50 KB）: 圧縮率 98%

### ヘッダー

**リクエストヘッダー:**
```
X-Unique-Request-Identifier: <UUID v4>
```

**レスポンスヘッダー:**
```
X-Idempotency-Cache: HIT | MISS
```

## 設定

### 環境変数 (.env)

```bash
# キャッシュストア（必須）
CACHE_STORE=redis

# Redis接続設定
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CLIENT=phpredis

# 冪等性機能の設定
IDEMPOTENCY_ENABLED=true
IDEMPOTENCY_CACHE_TTL=86400  # 24時間
```

### config/idempotency.php

```php
return [
    'enabled' => env('IDEMPOTENCY_ENABLED', true),
    'cache_ttl' => env('IDEMPOTENCY_CACHE_TTL', 86400),
    'cache_prefix' => 'idempotency',
];
```

## 適用対象

### 有効なエンドポイント

- ✅ 認証が必要な全てのPOSTエンドポイント
- ✅ `/api/unit/level_up`
- ✅ `/api/equipment/level_up`
- ✅ `/api/mailbox/receive`
- ✅ `/api/in_app_purchase/buy`
- ✅ その他全ての認証済みPOSTリクエスト

### 無効なケース

- ❌ GETリクエスト（元々冪等）
- ❌ 認証不要のエンドポイント（`/auth/sign_up`, `/auth/sign_in`等）
- ❌ `X-Unique-Request-Identifier`ヘッダーが無い場合

## クライアント実装

### UUID生成

```javascript
// UUIDライブラリを使用
import { v4 as uuidv4 } from 'uuid';

// リクエスト作成
function createRequest(endpoint, data) {
  const uniqueRequestId = uuidv4();
  
  return fetch(endpoint, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'X-Unique-Request-Identifier': uniqueRequestId,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  });
}
```

### リトライ処理

```javascript
async function requestWithRetry(endpoint, data, maxRetries = 3) {
  // 同じリクエストIDでリトライ
  const uniqueRequestId = uuidv4();
  
  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${accessToken}`,
          'X-Unique-Request-Identifier': uniqueRequestId, // 同じID
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
      });
      
      // キャッシュヒットを確認
      const cacheStatus = response.headers.get('X-Idempotency-Cache');
      if (cacheStatus === 'HIT') {
        console.log('Returned cached response (duplicate request)');
      }
      
      return response;
      
    } catch (error) {
      if (attempt === maxRetries) throw error;
      console.log(`Retry ${attempt}/${maxRetries}`);
      await sleep(1000 * attempt); // exponential backoff
    }
  }
}
```

## テスト

### 基本テスト

```bash
# Docker環境で実行
docker exec api-php php /var/www/html/test_idempotency.php
```

### 圧縮効果テスト

```bash
# gzip圧縮の効果を確認
docker exec api-php php /var/www/html/test_compression.php
```

### 統合テスト

```bash
# リトライシナリオのテスト
docker exec api-php php /var/www/html/test_idempotency_integration.php
```

### 手動テスト

```bash
# 同じUUIDで2回リクエスト
UUID="550e8400-e29b-41d4-a716-446655440000"

# 1回目（MISS）
curl -X POST https://api.example.com/api/unit/level_up \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-Unique-Request-Identifier: ${UUID}" \
  -H "Content-Type: application/json" \
  -d '{"trx_unit_id":1,"mst_item_id":"exp_potion","use_count":1}' \
  -i | grep X-Idempotency-Cache
# X-Idempotency-Cache: MISS

# 2回目（HIT - キャッシュされたレスポンスを返す）
curl -X POST https://api.example.com/api/unit/level_up \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-Unique-Request-Identifier: ${UUID}" \
  -H "Content-Type: application/json" \
  -d '{"trx_unit_id":1,"mst_item_id":"exp_potion","use_count":1}' \
  -i | grep X-Idempotency-Cache
# X-Idempotency-Cache: HIT
```

## 監視

### Redisキーの確認

```bash
# Redisに保存されているキーを確認
docker exec redis redis-cli --scan --pattern "*idempotency*"

# TTLを確認
docker exec redis redis-cli TTL "laravel_database_idempotency:1:uuid:api:unit:level_up"

# キャッシュサイズを確認（圧縮後）
docker exec redis redis-cli --raw GET "laravel_database_idempotency:1:uuid:api:unit:level_up" | wc -c
```

### キャッシュヒット率

レスポンスヘッダー `X-Idempotency-Cache` を集計してキャッシュヒット率を監視できます。

### メモリ使用量

```bash
# Redis全体のメモリ使用量
docker exec redis redis-cli INFO memory | grep used_memory_human

# 圧縮によるメモリ削減効果
# - 圧縮なし: 1,000リクエスト × 50KB = 50MB
# - 圧縮あり: 1,000リクエスト × 0.76KB = 0.76MB（98%削減）
```

## 注意事項

1. **UUIDの一意性**: クライアントは必ずリクエストごとに新しいUUIDを生成する
2. **リトライ時は同じID**: ネットワークエラー時のリトライでは同じUUIDを使用
3. **課金処理**: 課金APIでは特に重要（重複課金を防止）
4. **Redisの可用性**: Redisがダウンしても冪等性チェックはスキップして処理を継続
5. **圧縮レベル**: `IdempotencyMiddleware::COMPRESSION_LEVEL = 6`（デフォルト）でバランス良好
   - レベル1-3: 高速だが圧縮率低い
   - レベル4-6: バランス良好（推奨）
   - レベル7-9: 高圧縮率だが処理時間増加

## トラブルシューティング

### キャッシュが効かない

```bash
# 設定確認
docker exec api-php php artisan tinker
>>> config('idempotency.enabled')
=> true
>>> config('cache.default')
=> "redis"

# Redis接続確認
>>> Cache::get('test')
```

### Redis接続エラー

```bash
# Redisコンテナの状態確認
docker ps | grep redis

# Redis接続テスト
docker exec redis redis-cli ping
# PONG
```

## 実装ファイル

- `app/Http/Middleware/IdempotencyMiddleware.php` - ミドルウェア本体（gzip圧縮/解凍機能含む）
- `config/idempotency.php` - 設定ファイル
- `app/Http/Requests/_BaseRequest.php` - ヘルパーメソッド
- `bootstrap/app.php` - ミドルウェア登録
- `routes/api.php` - ルート設定
- `test_idempotency.php` - 基本動作テスト
- `test_compression.php` - 圧縮効果テスト
- `test_idempotency_integration.php` - 統合テスト

## パフォーマンス

### 圧縮オーバーヘッド

- **圧縮時間**: ~1ms（2KB）、~5ms（50KB）
- **解凍時間**: ~0.5ms（2KB）、~2ms（50KB）
- **メモリ削減**: 89-98%（レスポンスサイズに依存）

### スループット

- 圧縮によるCPUオーバーヘッドは小さく、Redisのメモリ削減効果の方が大きい
- 大量のリクエストをキャッシュする場合、圧縮により大幅にコスト削減可能
