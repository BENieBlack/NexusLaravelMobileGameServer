<?php

namespace App\Adapters\Chat;

use NexusChat\Constants\ChatRoomRole;
use NexusChat\DataTransferObjects\ChatRoomMember;

/**
 * ChatRoomMemberAdapter
 *
 * chat_room_member 1 行を DTO（ChatRoomMember）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class ChatRoomMemberAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): ChatRoomMember
    {
        return new ChatRoomMember(
            id: (int) $row['id'],
            chatRoomId: (int) $row['chat_room_id'],
            playerId: (int) $row['sys_player_id'],
            playerName: (string) $row['player_name'],
            role: ChatRoomRole::from((string) $row['role']),
            joinedAt: (string) $row['joined_at'],
        );
    }
}
