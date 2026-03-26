<?php

/**
 * フレンド削除API テストスクリプト
 * 
 * 使い方:
 * 1. Dockerコンテナ内で実行: docker exec -it api-container php test_friend_delete.php
 * 2. または: php test_friend_delete.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sys\SysFriendApply;
use App\Models\Sys\SysPlayer;
use App\Domain\Friend\UseCases\DeleteUseCase;
use App\Repositories\Sys\SysFriendApplyRepository;
use App\Repositories\Sys\SysPlayerRepository;

echo "=== フレンド削除API テスト ===\n\n";

// テストデータの確認
echo "1. テストデータの確認\n";
$playerCount = SysPlayer::count();
echo "   - プレイヤー数: {$playerCount}\n";

$acceptedCount = SysFriendApply::where('status', SysFriendApply::STATUS_ACCEPTED)->count();
echo "   - 承認済みフレンド数: {$acceptedCount}\n\n";

// テスト用のフレンド関係を取得
$friendRelation = SysFriendApply::where('status', SysFriendApply::STATUS_ACCEPTED)
    ->with(['sendPlayer', 'receivePlayer'])
    ->first();

if (!$friendRelation) {
    echo "⚠️  承認済みフレンド関係が見つかりません\n";
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

echo "2. テストフレンド関係\n";
echo "   - Friend Apply ID: {$friendRelation->id}\n";
echo "   - Sender: {$friendRelation->sendPlayer->name} (my_id: {$friendRelation->sendPlayer->my_id})\n";
echo "   - Receiver: {$friendRelation->receivePlayer->name} (my_id: {$friendRelation->receivePlayer->my_id})\n";
echo "   - Status: {$friendRelation->status}\n\n";

// 削除実行者（Sender）の視点でテスト
$executorPlayer = $friendRelation->sendPlayer;
$targetPlayer = $friendRelation->receivePlayer;

echo "3. 削除実行\n";
echo "   - 実行者: {$executorPlayer->name} (ID: {$executorPlayer->id})\n";
echo "   - 削除対象: {$targetPlayer->name} (my_id: {$targetPlayer->my_id})\n\n";

// DeleteUseCaseを実行
echo "4. DeleteUseCase実行\n";
try {
    $friendRepository = new SysFriendApplyRepository();
    $playerRepository = new SysPlayerRepository();
    $useCase = new DeleteUseCase($friendRepository, $playerRepository);
    
    $response = $useCase->handle($executorPlayer->id, $targetPlayer->my_id);
    
    echo "   ✓ 正常に削除されました\n\n";
    
    echo "5. レスポンス内容\n";
    $responseData = $response->toArray();
    echo "   - success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
    echo "   - deleted_my_id: {$responseData['deleted_my_id']}\n";
    echo "   - message: {$responseData['message']}\n\n";
    
    // 削除確認
    echo "6. 削除確認\n";
    $deletedRelation = SysFriendApply::withTrashed()->find($friendRelation->id);
    if ($deletedRelation->trashed()) {
        echo "   ✓ フレンド関係が論理削除されています\n";
        echo "   - deleted_at: {$deletedRelation->deleted_at}\n";
    } else {
        echo "   ❌ フレンド関係が削除されていません\n";
    }
    
    // 残りの承認済みフレンド数
    $remainingCount = SysFriendApply::where('status', SysFriendApply::STATUS_ACCEPTED)->count();
    echo "   - 残りの承認済みフレンド数: {$remainingCount}\n";
    
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

// エラーケースのテスト
echo "\n=== エラーケーステスト ===\n\n";

echo "7. 存在しないフレンドの削除テスト\n";
try {
    $nonExistentMyId = "NONEXISTENT_MY_ID_12345";
    $friendRepository = new SysFriendApplyRepository();
    $playerRepository = new SysPlayerRepository();
    $useCase = new DeleteUseCase($friendRepository, $playerRepository);
    
    $response = $useCase->handle($executorPlayer->id, $nonExistentMyId);
    echo "   ❌ エラーが発生すべきでしたが成功しました\n";
} catch (\App\Exceptions\GameException $e) {
    echo "   ✓ 期待通りエラーが発生しました\n";
    echo "   - エラーコード: {$e->getErrorCode()}\n";
    echo "   - メッセージ: {$e->getMessage()}\n";
}

echo "\n✅ 全テスト完了\n";
