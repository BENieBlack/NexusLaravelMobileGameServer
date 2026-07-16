<?php

use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GachaController;
use App\Http\Controllers\InAppPurchaseController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

// メンテナンス状態確認エンドポイント（認証不要）
Route::get('/maintenance/status', [AdminMaintenanceController::class, 'status']);

// Auth endpoints with action-based routing
// 認証不要のエンドポイント（最初のアクセス時に必要）
// sign_upはクライアント署名検証とレート制限を適用
Route::middleware(['client.signature', 'throttle.signup'])->group(function () {
    Route::post('/auth/sign_up', [AuthController::class, 'signUp']);
});

Route::post('/auth/sign_in', [AuthController::class, 'signIn']);
Route::post('/auth/refresh_token', [AuthController::class, 'refreshToken']);

// バージョンチェックは認証なしでもアクセス可能（GETのみ）
Route::get('/auth/version', [AuthController::class, 'version']);

// Protected endpoints (require access token)
// idempotencyミドルウェアを追加して重複リクエストを防止
Route::middleware(['auth.token', 'idempotency'])->group(function () {
    // バージョンチェック（認証済み、POST）
    Route::post('/auth/version', [AuthController::class, 'version']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/player/me', [PlayerController::class, 'me']);
    
    // In-App Purchase endpoints
    Route::post('/in_app_purchase/buy', [InAppPurchaseController::class, 'buy']);
    
    // Unit endpoints
    Route::post('/unit/level_up', [UnitController::class, 'levelUp']);
    
    // Equipment endpoints
    Route::post('/equipment/level_up', [EquipmentController::class, 'levelUp']);
    
    // Friend endpoints
    Route::post('/friend/apply/send', [FriendController::class, 'applySend']);
    Route::post('/friend/apply/accept', [FriendController::class, 'applyAccept']);
    Route::post('/friend/apply/reject', [FriendController::class, 'applyReject']);
    Route::get('/friend/apply/list', [FriendController::class, 'applyList']);
    Route::get('/friend/list', [FriendController::class, 'list']);
    Route::post('/friend/delete', [FriendController::class, 'delete']);
    
    // Mailbox endpoints
    Route::get('/mailbox/list', [MailboxController::class, 'list']);
    Route::post('/mailbox/open', [MailboxController::class, 'open']);
    Route::post('/mailbox/receive', [MailboxController::class, 'receive']);
    Route::post('/mailbox/receive_all', [MailboxController::class, 'receiveAll']);
    Route::post('/mailbox/protect', [MailboxController::class, 'protect']);
    
    // Gacha endpoints
    Route::post('/gacha/draw', [GachaController::class, 'draw']);
});

// Legacy signup endpoint (for backward compatibility - consider deprecating)
Route::middleware(['client.signature', 'throttle.signup'])->group(function () {
    Route::post('/signup', [AuthController::class, 'signUp']);
});

// 管理者用メンテナンスAPIエンドポイント
// TODO: 適切な認証ミドルウェア（例: auth.admin）を追加すること
Route::prefix('admin')->group(function () {
    Route::post('/maintenance/start', [AdminMaintenanceController::class, 'start']);
    Route::post('/maintenance/end', [AdminMaintenanceController::class, 'end']);
});
