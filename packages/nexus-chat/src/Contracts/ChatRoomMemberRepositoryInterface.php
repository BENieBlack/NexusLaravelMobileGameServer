<?php

namespace NexusChat\Contracts;

use NexusChat\DataTransferObjects\ChatRoomMember;
use NexusChat\Constants\ChatRoomRole;

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
     * @param  int  $playerId    プレイヤーID
     */
    public function selectByRoomAndPlayer(int $chatRoomId, int $playerId): ?ChatRoomMember;

    /**
     * メンバーを追加（招待時）
     *
     * @param  int           $chatRoomId   チャットルームID
     * @param  int           $playerId     追加するプレイヤーID
     * @param  string        $playerName   表示名（非正規化）
     * @param  ChatRoomRole  $role         付与するロール
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
     * @param  int           $chatRoomId  チャットルームID
     * @param  int           $playerId    対象プレイヤーID
     * @param  ChatRoomRole  $newRole     新しいロール
     */
    public function updateRole(int $chatRoomId, int $playerId, ChatRoomRole $newRole): void;

    /**
     * メンバーを削除（退室・キック）
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $playerId    対象プレイヤーID
     */
    public function delete(int $chatRoomId, int $playerId): void;

    /**
     * ルームの現在のメンバー数を取得
     *
     * @param  int  $chatRoomId  チャットルームID
     */
    public function countByRoomId(int $chatRoomId): int;
}
