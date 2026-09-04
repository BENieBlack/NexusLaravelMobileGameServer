<?php

namespace NexusChat\Events;

use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * MessageSent
 *
 * チャットメッセージが送信された時に発火するブロードキャストイベント
 *
 * Laravel Reverb がこのイベントを受け取り、
 * 対象チャンネルの接続クライアントへリアルタイム配信する
 *
 * フレンドチャット: private-chat.friend.{room_key}
 * ギルドチャット:   presence-chat.guild.{room_key}
 */
class MessageSent
{
    public function __construct(
        public readonly ChatRoom $room,
        public readonly ChatMessage $message,
    ) {}

    /**
     * ブロードキャスト先チャンネル名
     */
    public function broadcastOn(): string
    {
        return $this->room->getChannelName();
    }

    /**
     * ブロードキャストデータ
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->getId(),
            'chat_room_id' => $this->message->getChatRoomId(),
            'sender_player_id' => $this->message->getSenderPlayerId(),
            'sender_name' => $this->message->getSenderName(),
            'body' => $this->message->getBody(),
            'created_at' => $this->message->getCreatedAt(),
        ];
    }

    /**
     * ブロードキャストイベント名
     */
    public function broadcastAs(): string
    {
        return 'chat.message';
    }
}
