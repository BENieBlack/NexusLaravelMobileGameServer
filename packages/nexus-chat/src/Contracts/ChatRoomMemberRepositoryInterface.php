<?php

namespace NexusChat\Contracts;

use NexusChat\Constants\ChatRoomRole;
use NexusChat\DataTransferObjects\ChatRoomMember;

/**
 * ChatRoomMemberRepositoryInterface
 *
 * グループチャットのメンバー管理インターフェース
 * Application層のRepositoryAdapterが実装する
 */
interface ChatRoomMemberRepositoryInterface
{
    /**
     * チャットルームのメンバー一覧を取得
     *
     * @param  int  $chatRoomId  チャットルームID
     * @return array<ChatRoomMember>
     */
    public function selectByRoomId(int $chatRoomId): array;

    /**
     * プレイヤーがルームのメンバーか取得
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByRoomAndPlayer(int $chatRoomId, int $playerId): ?ChatRoomMember;

    /**
     * プレイヤーがルームのメンバーか、行ロック付き（current read）で取得
     *
     * REPEATABLE READ では普通の SELECT がロック待ちの後も古いスナップショットを読むため、
     * ルーム行のロックを取った後の権限・重複の判定にはこちらを使う。
     * トランザクションの外で呼ぶと例外になる。
     */
    public function selectByRoomAndPlayerForUpdate(int $chatRoomId, int $playerId): ?ChatRoomMember;

    /**
     * メンバーを追加（招待時）
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $playerId  追加するプレイヤーID
     * @param  string  $playerName  表示名（非正規化）
     * @param  ChatRoomRole  $role  付与するロール
     */
    public function insert(
        int $chatRoomId,
        int $playerId,
        string $playerName,
        ChatRoomRole $role,
    ): ChatRoomMember;

    /**
     * メンバーのロールを更新
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $playerId  対象プレイヤーID
     * @param  ChatRoomRole  $newRole  新しいロール
     */
    public function updateRole(int $chatRoomId, int $playerId, ChatRoomRole $newRole): void;

    /**
     * メンバーを削除（退室・キック）
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $playerId  対象プレイヤーID
     */
    public function delete(int $chatRoomId, int $playerId): void;

    /**
     * ルームの現在のメンバー数を取得
     *
     * @param  int  $chatRoomId  チャットルームID
     */
    public function countByRoomId(int $chatRoomId): int;

    /**
     * ルームの現在のメンバー数を、行ロック付き（current read）で取得
     *
     * 人数の上限判定と member_count の書き直しに使う。
     * トランザクションの外で呼ぶと例外になる。
     *
     * @param  int  $chatRoomId  チャットルームID
     */
    public function countByRoomIdForUpdate(int $chatRoomId): int;
}
