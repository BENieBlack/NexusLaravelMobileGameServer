<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GachaController;
use App\Http\Controllers\InAppPurchaseController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

// Auth endpoints with action-based routing
// 認証不要のエンドポイント（最初のアクセス時に必要）
Route::post('/auth/sign_up', [AuthController::class, 'signUp']);
Route::post('/auth/sign_in', [AuthController::class, 'signIn']);
Route::post('/auth/refresh_token', [AuthController::class, 'refreshToken']);

// Protected endpoints (require access token)
// idempotencyミドルウェアを追加して重複リクエストを防止
Route::middleware(['auth.token', 'idempotency'])->group(function () {
    // version は認証必須（攻撃対象にならないため）
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
    
    // Gacha endpoints
    Route::post('/gacha/draw', [GachaController::class, 'draw']);
});

// Legacy signup endpoint (for backward compatibility - consider deprecating)
Route::post('/signup', [AuthController::class, 'signUp']);
