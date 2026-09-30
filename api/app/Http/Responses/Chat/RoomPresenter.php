<?php

namespace App\Http\Responses\Chat;

use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\DataTransferObjects\ChatRoomMember;

/**
 * RoomPresenter
 *
 * チャットのDTOをレスポンス用の配列へ写す
 *
 * 同じ形をルーム一覧・単体・メッセージ送信の各レスポンスで使うため、
 * キーの定義をここ1箇所に置く
 */
class RoomPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ChatRoom $room): array
    {
        return [
            'sys_chat_room_id' => $room->getId(),
            'type' => $room->getType()->value,
            'room_key' => $room->getRoomKey(),
            'name' => $room->getName(),
            'sys_guild_id' => $room->getGuildId(),
            'member_count' => $room->getMemberCount(),
            'channel_name' => $room->getChannelName(),
            'created_at' => $room->getCreatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function messageToArray(ChatMessage $message): array
    {
        return [
            'sys_chat_message_id' => $message->getId(),
            'sys_chat_room_id' => $message->getChatRoomId(),
            'sender_sys_player_id' => $message->getSenderPlayerId(),
            'sender_name' => $message->getSenderName(),
            'body' => $message->getBody(),
            'created_at' => $message->getCreatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function memberToArray(ChatRoomMember $member): array
    {
        return [
            'sys_chat_room_id' => $member->getChatRoomId(),
            'sys_player_id' => $member->getPlayerId(),
            'player_name' => $member->getPlayerName(),
            'role' => $member->getRole()->value,
            'joined_at' => $member->getJoinedAt(),
        ];
    }
}
