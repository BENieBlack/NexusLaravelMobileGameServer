<?php

namespace NexusChat\Contracts;

use NexusChat\Constants\ChatRoomType;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * ChatRoomRepositoryInterface
 *
 * チャットルームの永続化インターフェース
 * Application層のRepositoryAdapterが実装する
 */
interface ChatRoomRepositoryInterface
{
    /**
     * IDでチャットルームを取得
     */
    public function selectById(int $id): ?ChatRoom;

    /**
     * room_keyでチャットルームを取得
     */
    public function selectByRoomKey(string $roomKey): ?ChatRoom;

    /**
     * フレンドチャットルームを取得または作成する
     *
     * room_key = "{小さいプレイヤーID}_{大きいプレイヤーID}" として一意に管理
     *
     * @param  int  $playerIdA  プレイヤーID
     * @param  int  $playerIdB  プレイヤーID
     */
    public function findOrCreateFriendRoom(int $playerIdA, int $playerIdB): ChatRoom;

    /**
     * ギルドチャットルームを取得または作成する
     *
     * @param  int  $guildId  ギルドID
     */
    public function findOrCreateGuildRoom(int $guildId): ChatRoom;

    /**
     * グループチャットルームを作成する
     *
     * @param  string  $name  グループ名
     */
    public function createGroupRoom(string $name): ChatRoom;

    /**
     * プレイヤーが参加しているルーム一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @param  ChatRoomType[]  $types  フィルタする種別（空=全種別）
     * @return array<ChatRoom>
     */
    public function selectRoomsByPlayerId(int $playerId, array $types = []): array;

    /**
     * メンバー数を更新（insert/delete時にデノーマライズ）
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $memberCount  新しいメンバー数
     */
    public function updateMemberCount(int $chatRoomId, int $memberCount): void;
}
