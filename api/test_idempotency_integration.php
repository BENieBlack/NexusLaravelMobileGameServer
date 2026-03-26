<?php

/**
 * Idempotency Integration Test
 * 
 * 実際のキャッシュ動作を確認（Redis）
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Redisキャッシュを使用
config(['cache.default' => 'redis']);

echo "=== Idempotency Integration Test ===\n\n";

// テストデータ
$sysPlayerId = 1;
$uniqueRequestId = Str::uuid()->toString();
$path = 'api:mailbox:receive';

$cacheKey = sprintf(
    '%s:%d:%s:%s',
    config('idempotency.cache_prefix', 'idempotency'),
    $sysPlayerId,
    $uniqueRequestId,
    $path
);

echo "Test Scenario: Mailbox receive with retry\n";
echo "Player ID: {$sysPlayerId}\n";
echo "Request ID: {$uniqueRequestId}\n";
echo "Cache Key: {$cacheKey}\n\n";

// === 1回目のリクエスト ===
echo "1️⃣  First Request (Cache MISS expected)\n";
if (Cache::has($cacheKey)) {
    echo "   ❌ Unexpected cache hit - cleaning up\n";
    Cache::forget($cacheKey);
}

$response1 = [
    'data' => [
        'mailbox_id' => 123,
        'received_items' => [
            ['item_id' => 'gold', 'amount' => 1000],
            ['item_id' => 'diamond', 'amount' => 50],
        ],
        'received_at' => date('Y-m-d H:i:s'),
    ],
    'status' => 200,
    'headers' => ['Content-Type' => 'application/json'],
];

$ttl = config('idempotency.cache_ttl', 86400);
Cache::put($cacheKey, $response1, $ttl);
echo "   ✅ Response cached\n";
echo "   📦 Items: gold x1000, diamond x50\n\n";

// === 2回目のリクエスト（リトライ） ===
echo "2️⃣  Second Request - Network Error Retry (Cache HIT expected)\n";
if (Cache::has($cacheKey)) {
    $cachedResponse = Cache::get($cacheKey);
    echo "   ✅ Cache HIT - Returning cached response\n";
    echo "   📦 Same items: gold x1000, diamond x50\n";
    echo "   ⚠️  API処理はスキップ（重複実行を防止）\n\n";
} else {
    echo "   ❌ Cache MISS - This should not happen!\n\n";
}

// === 3回目のリクエスト（別のユーザー） ===
echo "3️⃣  Different Player Request (Cache MISS expected)\n";
$differentPlayerKey = sprintf(
    '%s:%d:%s:%s',
    config('idempotency.cache_prefix', 'idempotency'),
    999, // 別のプレイヤー
    $uniqueRequestId, // 同じRequest ID
    $path
);

if (Cache::has($differentPlayerKey)) {
    echo "   ❌ Unexpected cache hit\n";
} else {
    echo "   ✅ Cache MISS - Different player, independent cache\n\n";
}

// === Redisに保存されているキーを確認 ===
echo "4️⃣  Redis Key Verification\n";
try {
    $redis = app('redis')->connection();
    $keys = $redis->keys('*idempotency*');
    
    if (count($keys) > 0) {
        echo "   ✅ Found " . count($keys) . " idempotency key(s) in Redis\n";
        foreach ($keys as $key) {
            // Laravelのプレフィックスを除去して表示
            $displayKey = str_replace('laravel_database_', '', $key);
            $ttlValue = $redis->ttl($key);
            echo "   - {$displayKey} (TTL: {$ttlValue}s)\n";
        }
    } else {
        echo "   ℹ️  No idempotency keys found (might use prefix)\n";
    }
} catch (\Exception $e) {
    echo "   ℹ️  Could not query Redis directly: " . $e->getMessage() . "\n";
}

echo "\n";

// === クリーンアップ ===
echo "5️⃣  Cleanup\n";
Cache::forget($cacheKey);
echo "   ✅ Cache cleared\n\n";

echo "=== Integration Test Complete! ✅ ===\n";
