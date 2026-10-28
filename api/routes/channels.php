<?php

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| チャンネル認証
|--------------------------------------------------------------------------
|
| プライベートチャンネルの認証定義。
| Pusher プロトコルの /broadcasting/auth エンドポイントで使用される。
|
| ※ このエンドポイントには auth.token ミドルウェアを適用するため、
|   ApiSession にはすでにプレイヤーIDが設定されている。
|
*/

// プレイヤー通知チャンネル: private-notification.{sysPlayerId}
// 認証済みプレイヤーが自分自身のチャンネルにのみ接続できる
Broadcast::channel('notification.{sysPlayerId}', function ($user, string $sysPlayerId): bool {
    // ApiSession からプレイヤーIDを取得して照合する
    if (! ApiSession::hasSysPlayerId()) {
        return false;
    }

    return ApiSession::getSysPlayerId() === (int) $sysPlayerId;
});
