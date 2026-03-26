<?php

/**
 * フレンドリストAPI テストスクリプト
 * 
 * 使い方:
 * 1. Dockerコンテナ内で実行: docker exec -it api-container php test_friend_list.php
 * 2. または: php artisan tinker < test_friend_list.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sys\SysFriendApply;
use App\Models\Sys\SysPlayer;
use App\Domain\Friend\UseCases\ListUseCase;
use App\Repositories\Sys\SysFriendApplyRepository;

echo "=== フレンドリストAPI テスト ===\n\n";

// テストデータの確認
echo "1. テストデータの確認\n";
$playerCount = SysPlayer::count();
echo "   - プレイヤー数: {$playerCount}\n";

$applyCount = SysFriendApply::count();
echo "   - フレンド申請数: {$applyCount}\n";

$acceptedCount = SysFriendApply::where('status', SysFriendApply::STATUS_ACCEPTED)->count();
echo "   - 承認済みフレンド数: {$acceptedCount}\n\n";

// テストプレイヤーを取得（承認済みフレンドがいるプレイヤー）
$testPlayer = SysPlayer::whereHas('sentFriendApplies', function ($query) {
    $query->where('status', SysFriendApply::STATUS_ACCEPTED);
})->orWhereHas('receivedFriendApplies', function ($query) {
    $query->where('status', SysFriendApply::STATUS_ACCEPTED);
})->first();

if (!$testPlayer) {
    echo "⚠️  承認済みフレンドを持つプレイヤーが見つかりません\n";
    echo "   テストデータを作成してください\n\n";
    
    // テストデータ作成例
    echo "テストデータ作成例:\n";
    echo "---\n";
    echo "\$player1 = SysPlayer::first();\n";
    echo "\$player2 = SysPlayer::skip(1)->first();\n";
    echo "\n";
    echo "SysFriendApply::create([\n";
    echo "    'sender_sys_player_id' => \$player1->id,\n";
    echo "    'receiver_sys_player_id' => \$player2->id,\n";
    echo "    'status' => 'Accepted',\n";
    echo "]);\n";
    echo "---\n";
    exit(1);
}

echo "2. テストプレイヤー情報\n";
echo "   - ID: {$testPlayer->id}\n";
echo "   - my_id: {$testPlayer->my_id}\n";
echo "   - name: {$testPlayer->name}\n\n";

// UseCaseをテスト
echo "3. ListUseCase実行\n";
try {
    $repository = new SysFriendApplyRepository();
    $useCase = new ListUseCase($repository);
    
    $response = $useCase->handle($testPlayer->id);
    
    echo "   ✓ 正常に実行されました\n\n";
    
    echo "4. レスポンス内容\n";
    $responseData = $response->toArray();
    echo "   - フレンド数: " . count($responseData['friends']) . "\n";
    
    if (!empty($responseData['friends'])) {
        echo "\n   フレンド一覧:\n";
        foreach ($responseData['friends'] as $index => $friend) {
            echo "   [" . ($index + 1) . "]\n";
            echo "      - my_id: {$friend['my_id']}\n";
            echo "      - name: {$friend['name']}\n";
            echo "      - level: {$friend['level']}\n";
            echo "      - created_at: {$friend['created_at']}\n";
        }
    }
    
    echo "\n✅ テスト完了\n";
    
} catch (\Exception $e) {
    echo "   ❌ エラーが発生しました\n";
    echo "   エラー: " . $e->getMessage() . "\n";
    echo "   ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n";
    echo "スタックトレース:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
