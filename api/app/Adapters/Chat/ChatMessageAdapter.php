<?php

namespace App\Adapters\Chat;

use NexusChat\DataTransferObjects\ChatMessage;

/**
 * ChatMessageAdapter
 *
 * chat_message 1 行を DTO（ChatMessage）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class ChatMessageAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): ChatMessage
    {
        return new ChatMessage(
            id: (int) $row['id'],
            chatRoomId: (int) $row['chat_room_id'],
            senderPlayerId: (int) $row['sender_player_id'],
            senderName: (string) $row['sender_name'],
            body: (string) $row['body'],
            isDeleted: (bool) $row['is_deleted'],
            createdAt: (string) $row['created_at'],
        );
    }
}
