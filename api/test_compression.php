<?php

/**
 * 圧縮効果テストスクリプト
 * 
 * 冪等性ミドルウェアのgzip圧縮機能の効果を確認する
 * 実際のレスポンスデータを使用して圧縮前後のサイズを比較する
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Cache;

// Laravelアプリケーションをブートストラップ
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 圧縮レベル（IdempotencyMiddlewareと同じ）
const COMPRESSION_LEVEL = 6;

// テスト用のレスポンスデータを生成（実際のゲームAPIレスポンスを模擬）
function generateTestResponses(): array
{
    return [
        'small' => [
            'data' => [
                'player_id' => 12345,
                'status' => 'success',
                'message' => 'Login successful',
            ],
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
        ],
        'medium' => [
            'data' => [
                'player_id' => 12345,
                'player_name' => 'TestPlayer',
                'level' => 50,
                'exp' => 125000,
                'coins' => 50000,
                'gems' => 1500,
                'items' => array_fill(0, 20, [
                    'item_id' => rand(1, 1000),
                    'item_name' => 'Item_' . rand(1, 100),
                    'quantity' => rand(1, 99),
                    'rarity' => ['common', 'rare', 'epic', 'legendary'][rand(0, 3)],
                ]),
                'quests' => array_fill(0, 10, [
                    'quest_id' => rand(1, 500),
                    'quest_name' => 'Quest_' . rand(1, 50),
                    'progress' => rand(0, 100),
                    'completed' => rand(0, 1) === 1,
                ]),
            ],
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
        ],
        'large' => [
            'data' => [
                'player_id' => 12345,
                'player_name' => 'TestPlayer',
                'level' => 50,
                'exp' => 125000,
                'stats' => [
                    'hp' => 1000,
                    'mp' => 500,
                    'attack' => 250,
                    'defense' => 200,
                    'speed' => 150,
                ],
                'inventory' => array_fill(0, 100, [
                    'item_id' => rand(1, 10000),
                    'item_name' => 'Item_' . rand(1, 1000),
                    'description' => 'This is a test item with a longer description to simulate real game data',
                    'quantity' => rand(1, 999),
                    'rarity' => ['common', 'rare', 'epic', 'legendary'][rand(0, 3)],
                    'attributes' => [
                        'attack_bonus' => rand(0, 50),
                        'defense_bonus' => rand(0, 50),
                        'hp_bonus' => rand(0, 100),
                    ],
                ]),
                'quest_log' => array_fill(0, 50, [
                    'quest_id' => rand(1, 1000),
                    'quest_name' => 'Quest_' . rand(1, 100),
                    'description' => 'Complete this quest to earn rewards. This is a longer description to simulate real quest data.',
                    'objectives' => array_fill(0, 3, [
                        'objective_id' => rand(1, 100),
                        'description' => 'Defeat 10 enemies',
                        'progress' => rand(0, 10),
                        'required' => 10,
                    ]),
                    'rewards' => [
                        'exp' => rand(100, 1000),
                        'coins' => rand(50, 500),
                        'items' => array_fill(0, 3, [
                            'item_id' => rand(1, 1000),
                            'quantity' => rand(1, 10),
                        ]),
                    ],
                ]),
            ],
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
        ],
    ];
}

// 圧縮テストを実行
function testCompression(string $label, array $data): void
{
    echo "\n=== {$label} ===\n";
    
    // JSONエンコード
    $jsonData = json_encode($data);
    $originalSize = strlen($jsonData);
    
    // gzip圧縮
    $compressed = gzencode($jsonData, COMPRESSION_LEVEL);
    $compressedSize = strlen($compressed);
    
    // 圧縮率を計算
    $compressionRatio = (1 - ($compressedSize / $originalSize)) * 100;
    
    // 結果を表示
    echo "元のサイズ:       " . number_format($originalSize) . " bytes\n";
    echo "圧縮後のサイズ:   " . number_format($compressedSize) . " bytes\n";
    echo "圧縮率:           " . number_format($compressionRatio, 2) . "%\n";
    echo "削減サイズ:       " . number_format($originalSize - $compressedSize) . " bytes\n";
    
    // 解凍テスト
    $decompressed = gzdecode($compressed);
    $decodedData = json_decode($decompressed, true);
    
    // データが正しく復元されたか確認
    if ($data === $decodedData) {
        echo "✓ 解凍テスト: 成功（データが正しく復元されました）\n";
    } else {
        echo "✗ 解凍テスト: 失敗（データが一致しません）\n";
    }
}

// Redis接続テスト
function testRedisCompression(): void
{
    echo "\n=== Redis圧縮テスト ===\n";
    
    try {
        // テストデータ
        $testData = [
            'data' => [
                'player_id' => 99999,
                'message' => 'Redis compression test',
                'items' => array_fill(0, 50, [
                    'id' => rand(1, 1000),
                    'name' => 'Test Item ' . rand(1, 100),
                    'value' => rand(1, 9999),
                ]),
            ],
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
        ];
        
        $jsonData = json_encode($testData);
        $compressed = gzencode($jsonData, COMPRESSION_LEVEL);
        
        $cacheKey = 'compression_test:' . time();
        
        // Redisにキャッシュ
        Cache::put($cacheKey, $compressed, 60);
        
        echo "✓ Redisへの保存: 成功\n";
        echo "キャッシュキー: {$cacheKey}\n";
        echo "元のサイズ: " . number_format(strlen($jsonData)) . " bytes\n";
        echo "圧縮後: " . number_format(strlen($compressed)) . " bytes\n";
        
        // Redisから取得
        $retrieved = Cache::get($cacheKey);
        
        if ($retrieved === $compressed) {
            echo "✓ Redisからの取得: 成功\n";
            
            // 解凍
            $decompressed = gzdecode($retrieved);
            $decoded = json_decode($decompressed, true);
            
            if ($decoded === $testData) {
                echo "✓ データ復元: 成功\n";
            } else {
                echo "✗ データ復元: 失敗\n";
            }
        } else {
            echo "✗ Redisからの取得: 失敗\n";
        }
        
        // クリーンアップ
        Cache::forget($cacheKey);
        echo "✓ テストキャッシュ削除: 完了\n";
        
    } catch (Exception $e) {
        echo "✗ エラー: " . $e->getMessage() . "\n";
    }
}

// メイン実行
echo "==================================================\n";
echo "IdempotencyMiddleware 圧縮効果テスト\n";
echo "圧縮レベル: " . COMPRESSION_LEVEL . "\n";
echo "==================================================\n";

$testResponses = generateTestResponses();

testCompression('小サイズレスポンス (ログイン)', $testResponses['small']);
testCompression('中サイズレスポンス (プレイヤー情報)', $testResponses['medium']);
testCompression('大サイズレスポンス (フルデータ)', $testResponses['large']);

testRedisCompression();

echo "\n==================================================\n";
echo "テスト完了\n";
echo "==================================================\n";
