<?php

use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GachaController;
use App\Http\Controllers\GuildController;
use App\Http\Controllers\InAppPurchaseController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RewardTrackController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

// =========================================
// メンテナンス除外エンドポイント
// =========================================
// 以下のエンドポイントはメンテナンス中でもアクセス可能
// （config/maintenance.php の excluded_routes で設定）

// メンテナンス状態確認エンドポイント（認証不要）
Route::get('/maintenance/status', [AdminMaintenanceController::class, 'status']);

// バージョンチェックは認証なしでもアクセス可能（GETのみ）
// メンテナンス情報を含むため、メンテ中でもアクセス可能
Route::get('/auth/version', [AuthController::class, 'version']);

// 管理者用メンテナンスAPIエンドポイント
// 認証: Bearer トークン認証 + オプションでIP制限
Route::prefix('admin')->middleware('auth.admin')->group(function () {
    Route::post('/maintenance/start', [AdminMaintenanceController::class, 'start']);
    Route::post('/maintenance/end', [AdminMaintenanceController::class, 'end']);
});

// =========================================
// メンテナンス判定対象エンドポイント
// =========================================
// 以下の全エンドポイントにメンテナンスチェックを適用
Route::middleware('maintenance')->group(function () {
    // Auth endpoints with action-based routing
    // 認証不要のエンドポイント（最初のアクセス時に必要）
    // sign_upはクライアント署名検証とレート制限を適用
    Route::middleware(['client.signature', 'throttle.signup'])->group(function () {
        Route::post('/auth/sign_up', [AuthController::class, 'signUp']);
    });

    // 認証情報を受け取るエンドポイントはレート制限を適用する
    Route::middleware('throttle.auth:sign_in')->group(function () {
        Route::post('/auth/sign_in', [AuthController::class, 'signIn']);
    });

    Route::middleware('throttle.auth:refresh_token')->group(function () {
        Route::post('/auth/refresh_token', [AuthController::class, 'refreshToken']);
    });

    // ギルドの参照系は加入前に見られる必要があるため認証不要で公開する。
    // ただし繰り返し叩けば他プレイヤーの所属を集められるため、IP単位で制限する
    Route::middleware('throttle.public:guild_read')->group(function () {
        Route::get('/guild/list', [GuildController::class, 'list']);
        Route::get('/guild/detail', [GuildController::class, 'detail']);
        Route::get('/guild/member/list', [GuildController::class, 'memberList']);
    });

    // Protected endpoints (require access token)
    // idempotencyミドルウェアを追加して重複リクエストを防止
    Route::middleware(['auth.token', 'idempotency'])->group(function () {
        // バージョンチェック（認証済み、POST）
        Route::post('/auth/version', [AuthController::class, 'version']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::get('/player/me', [PlayerController::class, 'me']);

        // In-App Purchase endpoints
        Route::post('/in_app_purchase/buy', [InAppPurchaseController::class, 'buy']);

        // Item endpoints
        Route::post('/item/use', [ItemController::class, 'use']);

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

        // アルバム（収集記録）
        Route::get('/album/list', [AlbumController::class, 'list']);
        Route::post('/friend/delete', [FriendController::class, 'delete']);

        // Guild endpoints
        Route::post('/guild/create', [GuildController::class, 'create']);
        Route::post('/guild/apply/send', [GuildController::class, 'applySend']);
        Route::post('/guild/apply/accept', [GuildController::class, 'applyAccept']);
        Route::post('/guild/apply/reject', [GuildController::class, 'applyReject']);
        Route::get('/guild/apply/list', [GuildController::class, 'applyList']);
        Route::post('/guild/leave', [GuildController::class, 'leave']);

        // Mailbox endpoints
        Route::get('/mailbox/list', [MailboxController::class, 'list']);
        Route::post('/mailbox/open', [MailboxController::class, 'open']);
        Route::post('/mailbox/receive', [MailboxController::class, 'receive']);
        Route::post('/mailbox/receive_all', [MailboxController::class, 'receiveAll']);
        Route::post('/mailbox/lock', [MailboxController::class, 'lock']);

        // Gacha endpoints
        Route::post('/gacha/draw', [GachaController::class, 'draw']);

        // Notification endpoints
        Route::get('/notification/list', [NotificationController::class, 'list']);
        Route::post('/notification/read', [NotificationController::class, 'read']);
        Route::post('/notification/read_all', [NotificationController::class, 'readAll']);

        // Chat endpoints
        // フレンドDM
        Route::post('/chat/friend/room', [ChatController::class, 'friendRoom']);
        Route::get('/chat/messages', [ChatController::class, 'messages']);
        Route::post('/chat/message/send', [ChatController::class, 'send']);
        Route::post('/chat/message/delete', [ChatController::class, 'deleteMessage']);
        // グループチャット
        Route::post('/chat/group/create', [ChatController::class, 'createGroup']);
        Route::post('/chat/group/invite', [ChatController::class, 'invite']);
        Route::post('/chat/group/kick', [ChatController::class, 'kick']);
        Route::post('/chat/group/leave', [ChatController::class, 'leaveGroup']);
        Route::post('/chat/group/role', [ChatController::class, 'changeRole']);
        Route::get('/chat/group/members', [ChatController::class, 'groupMembers']);
        // ギルドチャット
        Route::post('/chat/guild/room', [ChatController::class, 'guildRoom']);
        // 参加中ルーム一覧
        Route::get('/chat/rooms', [ChatController::class, 'rooms']);

        // =========================================
        // RewardTrack（バトルパス型報酬トラック）
        // =========================================
        // プレイヤーの進捗と報酬を扱うため auth.token が要る。
        // receive は配布を伴うので idempotency も外せない
        Route::prefix('reward-track')->group(function () {
            // トラックサマリー取得（進捗・所持ライン・受け取り済みマイルストーン）
            Route::get('/summary', [RewardTrackController::class, 'summary']);
            // マイルストーンの報酬を受け取る
            Route::post('/receive', [RewardTrackController::class, 'receive']);
        });
    });

    // Legacy signup endpoint (DEPRECATED - use /auth/signup instead)
    // このエンドポイントは後方互換性のために残されています
    // 新規実装では /auth/signup を使用してください
    // 将来のバージョンで削除される予定です
    Route::middleware(['client.signature', 'throttle.signup'])->group(function () {
        // DEPRECATED: 2026-08-01 - Use /auth/signup instead
        Route::post('/signup', [AuthController::class, 'signUp']);
    });
});
