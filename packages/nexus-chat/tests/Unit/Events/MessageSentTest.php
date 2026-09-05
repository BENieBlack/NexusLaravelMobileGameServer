<?php

namespace NexusChat\Tests\Unit\Events;

use NexusChat\Constants\ChatRoomType;
use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\Events\MessageSent;
use NexusChat\Exceptions\ChatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MessageSentイベントと例外のテスト
 *
 * broadcastWith の中身はクライアントが読むペイロードそのもの。
 * キー名が変わると受信側が壊れるため固定する。
 */
class MessageSentTest extends TestCase
{
    #[Test]
    public function 配信先はルームのチャンネル名になる(): void
    {
        $event = new MessageSent($this->makeRoom(), $this->makeMessage());

        $this->assertSame('private-chat.friend.100_200', $event->broadcastOn());
    }

    #[Test]
    public function 配信イベント名は固定(): void
    {
        $event = new MessageSent($this->makeRoom(), $this->makeMessage());

        $this->assertSame('chat.message', $event->broadcastAs());
    }

    #[Test]
    public function 配信ペイロードにはメッセージの内容が入る(): void
    {
        $event = new MessageSent($this->makeRoom(), $this->makeMessage());

        $this->assertSame([
            'message_id' => 5,
            'chat_room_id' => 3,
            'sender_player_id' => 100,
            'sender_name' => 'たろう',
            'body' => 'こんにちは',
            'created_at' => '2026-09-05 00:00:00',
        ], $event->broadcastWith());
    }

    #[Test]
    public function 例外はエラーコードを持つ(): void
    {
        // クライアントは message ではなく error_code で分岐する
        $this->assertSame('CHAT_ROOM_NOT_FOUND', ChatException::roomNotFound()->getErrorCode());
        $this->assertSame('CHAT_NOT_ROOM_MEMBER', ChatException::notRoomMember()->getErrorCode());
        $this->assertSame('CHAT_MESSAGE_TOO_LONG', ChatException::messageTooLong()->getErrorCode());
        $this->assertSame('CHAT_MESSAGE_EMPTY', ChatException::messageEmpty()->getErrorCode());
        $this->assertSame('CHAT_NOT_MESSAGE_OWNER', ChatException::notMessageOwner()->getErrorCode());
        $this->assertSame('CHAT_NOT_FRIENDS', ChatException::notFriends()->getErrorCode());
        $this->assertSame('CHAT_NOT_GUILD_MEMBER', ChatException::notGuildMember()->getErrorCode());
        $this->assertSame('CHAT_NO_INVITE_PERMISSION', ChatException::noInvitePermission()->getErrorCode());
        $this->assertSame('CHAT_NO_KICK_PERMISSION', ChatException::noKickPermission()->getErrorCode());
        $this->assertSame('CHAT_NO_ROLE_MANAGE_PERMISSION', ChatException::noRoleManagePermission()->getErrorCode());
        $this->assertSame('CHAT_ROOM_FULL', ChatException::roomFull()->getErrorCode());
        $this->assertSame('CHAT_ALREADY_MEMBER', ChatException::alreadyMember()->getErrorCode());
        $this->assertSame('CHAT_CANNOT_KICK_OWNER', ChatException::cannotKickOwner()->getErrorCode());
    }

    #[Test]
    public function 例外にはメッセージも入る(): void
    {
        $this->assertSame('Chat room not found.', ChatException::roomNotFound()->getMessage());
    }

    private function makeRoom(): ChatRoom
    {
        return new ChatRoom(
            id: 3,
            type: ChatRoomType::FRIEND,
            roomKey: '100_200',
            name: 'DM',
            guildId: null,
            memberCount: 2,
            createdAt: '2026-09-05 00:00:00',
        );
    }

    private function makeMessage(): ChatMessage
    {
        return new ChatMessage(
            id: 5,
            chatRoomId: 3,
            senderPlayerId: 100,
            senderName: 'たろう',
            body: 'こんにちは',
            isDeleted: false,
            createdAt: '2026-09-05 00:00:00',
        );
    }
}
