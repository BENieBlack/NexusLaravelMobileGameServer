<?php

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\Broadcast;
use Nexus\Core\Repositories\PlayerRepositoryInterface;
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

// プレイヤー通知チャンネル: private-notification.{myId}
// チャンネル名にはクライアントが知る公開 ID（sys_player.my_id）を使う。
// 内部の sys_player_id はクライアントへ返さないため、名前に含めない。
// 認証済みプレイヤーが自分自身のチャンネルにのみ接続できる
Broadcast::channel('notification.{myId}', function ($user, string $myId): bool {
    if (! ApiSession::hasSysPlayerId()) {
        return false;
    }

    // ApiSession のプレイヤーの my_id を引き、チャンネル名の my_id と照合する（1 回の主キー検索）
    /** @var PlayerRepositoryInterface $playerRepository */
    $playerRepository = app(PlayerRepositoryInterface::class);
    $player = $playerRepository->selectById(ApiSession::getSysPlayerId());

    return $player !== null && hash_equals($player->getMyId(), $myId);
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
