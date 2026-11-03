<?php

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\Broadcast;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;

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

// ギルドチャンネル: presence-guild.{guildId}
// ギルドメンバーのみ接続可能。プレゼンスチャンネルのためユーザー情報の配列を返す
Broadcast::channel('guild.{guildId}', function ($user, int $guildId): array|false {
    // ApiSession からプレイヤーIDを取得する（$user が null でも ApiSession から取得する）
    if (! ApiSession::hasSysPlayerId()) {
        return false;
    }

    $sysPlayerId = ApiSession::getSysPlayerId();

    // ギルドメンバーか確認する
    /** @var GuildMemberRepositoryInterface $guildMemberRepository */
    $guildMemberRepository = app(GuildMemberRepositoryInterface::class);
    $guildMember = $guildMemberRepository->selectByGuildAndPlayer($guildId, $sysPlayerId);

    if ($guildMember === null) {
        return false;
    }

    // プレゼンスチャンネルはユーザー情報の配列を返す必要がある
    return [
        'id' => $guildMember->getSysPlayerId(),
        'name' => (string) $guildMember->getSysPlayerId(),
    ];
});
