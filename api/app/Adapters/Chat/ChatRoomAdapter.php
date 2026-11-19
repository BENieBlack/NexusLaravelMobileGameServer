<?php

namespace App\Adapters\Chat;

use NexusChat\Constants\ChatRoomType;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * ChatRoomAdapter
 *
 * chat_room 1 行を DTO（ChatRoom）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class ChatRoomAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): ChatRoom
    {
        return new ChatRoom(
            id: (int) $row['id'],
            type: ChatRoomType::from((string) $row['type']),
            roomKey: (string) $row['room_key'],
            name: $row['name'] === null ? '' : (string) $row['name'],
            guildId: $row['sys_guild_id'] === null ? null : (int) $row['sys_guild_id'],
            memberCount: (int) $row['member_count'],
            createdAt: (string) $row['created_at'],
        );
    }
}
