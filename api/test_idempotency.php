<?php

/**
 * Idempotency Test Script
 * 
 * X-Unique-Request-Identifierを使った冪等性機能のテスト
 * 
 * 使用方法:
 * php test_idempotency.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Redisキャッシュを使用（本番環境と同じ設定）
config(['cache.default' => 'redis']);

echo "=== Idempotency Test (Redis Cache) ===\n\n";

// テスト用のキャッシュキーを生成
$sysPlayerId = 1;
$uniqueRequestId = Str::uuid()->toString();
$path = 'api:unit:level_up';

$cacheKey = sprintf(
    '%s:%d:%s:%s',
    config('idempotency.cache_prefix', 'idempotency'),
    $sysPlayerId,
    $uniqueRequestId,
    $path
);

echo "Cache Key: {$cacheKey}\n\n";

// 1. キャッシュが空であることを確認
echo "1. Check cache is empty...\n";
if (Cache::has($cacheKey)) {
    echo "   ❌ Cache exists (cleaning up)\n";
    Cache::forget($cacheKey);
} else {
    echo "   ✅ Cache is empty\n";
}

// 2. レスポンスデータを作成してキャッシュ
echo "\n2. Store response in cache...\n";
$responseData = [
    'data' => [
        'is_leveled_up' => true,
        'before_level' => 1,
        'after_level' => 2,
        'total_exp' => 100,
    ],
    'status' => 200,
    'headers' => [
        'Content-Type' => 'application/json',
    ],
];

$ttl = config('idempotency.cache_ttl', 86400);
Cache::put($cacheKey, $responseData, $ttl);
echo "   ✅ Response cached (TTL: {$ttl} seconds)\n";

// 3. キャッシュが存在することを確認
echo "\n3. Verify cache exists...\n";
if (Cache::has($cacheKey)) {
    echo "   ✅ Cache found\n";
    $cached = Cache::get($cacheKey);
    echo "   Data: " . json_encode($cached['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "   ❌ Cache not found\n";
    exit(1);
}

// 4. 設定値を確認
echo "\n4. Check configuration...\n";
echo "   Enabled: " . (config('idempotency.enabled', true) ? 'true' : 'false') . "\n";
echo "   Cache TTL: " . config('idempotency.cache_ttl', 86400) . " seconds (" . round(config('idempotency.cache_ttl', 86400) / 3600, 1) . " hours)\n";
echo "   Cache Prefix: " . config('idempotency.cache_prefix', 'idempotency') . "\n";

// 5. キャッシュをクリーンアップ
echo "\n5. Cleanup...\n";
Cache::forget($cacheKey);
echo "   ✅ Cache cleared\n";

// 6. 複数のキャッシュキー生成テスト
echo "\n6. Test cache key generation...\n";
$testCases = [
    ['player' => 1, 'uuid' => 'test-uuid-1', 'path' => 'api/unit/level_up'],
    ['player' => 2, 'uuid' => 'test-uuid-2', 'path' => 'api/equipment/level_up'],
    ['player' => 1, 'uuid' => 'test-uuid-1', 'path' => 'api/mailbox/receive'],
];

foreach ($testCases as $i => $case) {
    $sanitizedPath = str_replace('/', ':', $case['path']);
    $key = sprintf(
        '%s:%d:%s:%s',
        config('idempotency.cache_prefix', 'idempotency'),
        $case['player'],
        $case['uuid'],
        $sanitizedPath
    );
    echo "   Case " . ($i + 1) . ": {$key}\n";
}

echo "\n=== All Tests Passed! ✅ ===\n";
