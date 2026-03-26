<?php

/**
 * メールボックスAPI テストスクリプト
 * 
 * 使い方:
 * docker compose exec api-php php test_mailbox.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Trx\TrxMailbox;
use App\Models\Mst\MstMailbox;
use App\Domain\Mailbox\UseCases\ListUseCase;
use App\Domain\Mailbox\UseCases\OpenUseCase;
use App\Domain\Mailbox\UseCases\ReceiveUseCase;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Mst\MstMailboxRepository;
use App\Repositories\Trx\TrxMailboxRepository;
use App\Domain\Delivery\Services\DeliveryService;
use App\Utilities\ApiSession;

echo "=== メールボックスAPI テスト ===\n\n";

// Repositoryのインスタンス作成
$sysPlayerRepo = new SysPlayerRepository();
$mstMailboxRepo = new MstMailboxRepository();
$trxMailboxRepo = new TrxMailboxRepository();

// テストデータの確認
echo "1. テストデータの確認\n";

// プレイヤー数を取得（テスト用に直接クエリ）
$playerCount = \DB::connection('sys')->table('sys_player')->count();
echo "   - プレイヤー数: {$playerCount}\n";

// メールボックスマスター数を取得（テスト用に直接クエリ）
$mailboxCount = \DB::connection('mst')->table('mst_mailbox')->count();
echo "   - メールボックスマスター数: {$mailboxCount}\n";

// プレイヤーメール数を取得（テスト用に直接クエリ）
$trxMailboxCount = \DB::connection('trx1')->table('trx_mailbox')->count();
echo "   - プレイヤーメール数: {$trxMailboxCount}\n\n";

// テストプレイヤーを取得（Repository経由）
$testPlayer = $sysPlayerRepo->findById(1);

if (!$testPlayer) {
    echo "❌ プレイヤーID=1が存在しません\n";
    exit(1);
}

echo "2. テストプレイヤー情報\n";
echo "   - ID: {$testPlayer->id}\n";
echo "   - my_id: {$testPlayer->my_id}\n";
echo "   - name: {$testPlayer->name}\n\n";

$sysPlayerId = $testPlayer->id;

// ApiSessionを初期化（DeliveryServiceが内部で使用）
ApiSession::setSysPlayerId($sysPlayerId);

// ========================================
// TEST 1: メール一覧取得
// ========================================
echo "3. TEST 1: メール一覧取得 (ListUseCase)\n";
try {
    $listUseCase = new ListUseCase($trxMailboxRepo);
    
    $response = $listUseCase->handle($sysPlayerId);
    
    echo "   ✓ 正常に実行されました\n\n";
    
    echo "4. レスポンス内容\n";
    $responseData = $response->toArray();
    echo "   - メール数: " . count($responseData['mailbox_array']) . "\n";
    
    if (!empty($responseData['mailbox_array'])) {
        echo "\n   メール一覧:\n";
        foreach ($responseData['mailbox_array'] as $index => $mail) {
            echo "   [" . ($index + 1) . "]\n";
            echo "      - trx_mailbox_id: {$mail['trx_mailbox_id']}\n";
            echo "      - mst_mailbox_id: {$mail['mst_mailbox_id']}\n";
            echo "      - title: {$mail['title']}\n";
            echo "      - body: {$mail['body']}\n";
            echo "      - is_opened: " . ($mail['is_opened'] ? 'true' : 'false') . "\n";
            echo "      - is_received: " . ($mail['is_received'] ? 'true' : 'false') . "\n";
            echo "      - コンテンツ数: " . count($mail['content_array']) . "\n";
            
            if (!empty($mail['content_array'])) {
                foreach ($mail['content_array'] as $content) {
                    echo "         - {$content['content_type']}: {$content['content_id']} x{$content['amount']}\n";
                }
            }
        }
    }
    
    echo "\n✅ TEST 1 完了\n\n";
    
    // 最初のメールを取得（後続テストで使用）
    $firstMail = $responseData['mailbox_array'][0] ?? null;
    
} catch (\Exception $e) {
    echo "   ❌ エラーが発生しました\n";
    echo "   エラー: " . $e->getMessage() . "\n";
    echo "   ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n";
    echo "スタックトレース:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ========================================
// TEST 2: メール既読
// ========================================
if ($firstMail && !$firstMail['is_opened']) {
    echo "5. TEST 2: メール既読 (OpenUseCase)\n";
    try {
        $openUseCase = new OpenUseCase($trxMailboxRepo);
        
        $trxMailboxId = $firstMail['trx_mailbox_id'];
        $response = $openUseCase->handle($sysPlayerId, $trxMailboxId);
        
        echo "   ✓ 正常に実行されました\n";
        $responseData = $response->toArray();
        echo "   - trx_mailbox_id: {$responseData['trx_mailbox_id']}\n";
        echo "   - is_opened: " . ($responseData['is_opened'] ? 'true' : 'false') . "\n";
        echo "\n✅ TEST 2 完了\n\n";
        
    } catch (\Exception $e) {
        echo "   ❌ エラーが発生しました\n";
        echo "   エラー: " . $e->getMessage() . "\n";
        echo "   ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "\n";
        echo "スタックトレース:\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
} else {
    echo "5. TEST 2: メール既読 - スキップ（未読メールがありません）\n\n";
}

// ========================================
// TEST 3: 添付配布物受取
// ========================================
if ($firstMail && !$firstMail['is_received'] && !empty($firstMail['content_array'])) {
    echo "6. TEST 3: 添付配布物受取 (ReceiveUseCase)\n";
    try {
        $deliveryService = app(DeliveryService::class);
        $receiveUseCase = new ReceiveUseCase($trxMailboxRepo, $deliveryService);
        
        $trxMailboxId = $firstMail['trx_mailbox_id'];
        $response = $receiveUseCase->handle($sysPlayerId, $trxMailboxId);
        
        echo "   ✓ 正常に実行されました\n";
        $responseData = $response->toArray();
        echo "   - trx_mailbox_id: {$responseData['trx_mailbox_id']}\n";
        echo "   - is_received: " . ($responseData['is_received'] ? 'true' : 'false') . "\n";
        echo "   - 受け取ったコンテンツ:\n";
        
        foreach ($responseData['received_content'] as $content) {
            echo "      - {$content['type']}: {$content['id']} x{$content['amount']}\n";
        }
        
        echo "\n✅ TEST 3 完了\n\n";
        
    } catch (\Exception $e) {
        echo "   ❌ エラーが発生しました\n";
        echo "   エラー: " . $e->getMessage() . "\n";
        echo "   ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "\n";
        echo "スタックトレース:\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
} else {
    echo "6. TEST 3: 添付配布物受取 - スキップ（受取可能なメールがありません）\n\n";
}

echo "=== すべてのテスト完了 ===\n";
