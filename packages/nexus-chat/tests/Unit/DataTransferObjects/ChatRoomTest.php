<?php

namespace NexusChat\Tests\Unit\DataTransferObjects;

use NexusChat\Constants\ChatRoomRole;
use NexusChat\Constants\ChatRoomType;
use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\DataTransferObjects\ChatRoomMember;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * チャットのDTOのテスト
 *
 * チャンネル名はクライアントの購読先そのもので、変わると
 * リアルタイム配信が届かなくなるため文字列を固定する。
 */
class ChatRoomTest extends TestCase
{
    #[Test]
    public function フレンドの個別チャットはprivateチャンネルになる(): void
    {
        $room = $this->makeRoom(ChatRoomType::FRIEND, '100_200');

        $this->assertSame('private-chat.friend.100_200', $room->getChannelName());
    }

    #[Test]
    public function ギルドチャットはpresenceチャンネルになる(): void
    {
        // オンライン人数を見せるため presence を使う
        $room = $this->makeRoom(ChatRoomType::GUILD, '7');

        $this->assertSame('presence-chat.guild.7', $room->getChannelName());
    }

    #[Test]
    public function グループチャットはprivateチャンネルになる(): void
    {
        $room = $this->makeRoom(ChatRoomType::GROUP, '3');

        $this->assertSame('private-chat.group.3', $room->getChannelName());
    }

    #[Test]
    public function ルームの各値を取り出せる(): void
    {
        $room = new ChatRoom(
            id: 3,
            type: ChatRoomType::GUILD,
            roomKey: '7',
            name: 'ギルドチャット',
            guildId: 7,
            memberCount: 12,
            createdAt: '2026-09-05 00:00:00',
        );

        $this->assertSame(3, $room->getId());
        $this->assertSame(ChatRoomType::GUILD, $room->getType());
        $this->assertSame('7', $room->getRoomKey());
        $this->assertSame('ギルドチャット', $room->getName());
        $this->assertSame(7, $room->getGuildId());
        $this->assertSame(12, $room->getMemberCount());
        $this->assertSame('2026-09-05 00:00:00', $room->getCreatedAt());
    }

    #[Test]
    public function メッセージの各値を取り出せる(): void
    {
        $message = new ChatMessage(
            id: 5,
            chatRoomId: 3,
            senderPlayerId: 100,
            senderName: 'たろう',
            body: 'こんにちは',
            isDeleted: false,
            createdAt: '2026-09-05 00:00:00',
        );

        $this->assertSame(5, $message->getId());
        $this->assertSame(3, $message->getChatRoomId());
        $this->assertSame(100, $message->getSenderPlayerId());
        $this->assertSame('たろう', $message->getSenderName());
        $this->assertSame('こんにちは', $message->getBody());
        $this->assertFalse($message->isDeleted());
        $this->assertSame('2026-09-05 00:00:00', $message->getCreatedAt());
    }

    #[Test]
    public function メンバーはロールに応じた権限を返す(): void
    {
        $admin = $this->makeMember(ChatRoomRole::ADMIN);
        $member = $this->makeMember(ChatRoomRole::MEMBER);

        $this->assertTrue($admin->canInvite());
        $this->assertTrue($admin->canKick());
        $this->assertFalse($member->canInvite());
        $this->assertFalse($member->canKick());
    }

    #[Test]
    public function メンバーの各値を取り出せる(): void
    {
        $member = $this->makeMember(ChatRoomRole::OWNER);

        $this->assertSame(1, $member->getId());
        $this->assertSame(3, $member->getChatRoomId());
        $this->assertSame(100, $member->getPlayerId());
        $this->assertSame('たろう', $member->getPlayerName());
        $this->assertSame(ChatRoomRole::OWNER, $member->getRole());
        $this->assertSame('2026-09-05 00:00:00', $member->getJoinedAt());
    }

    private function makeRoom(ChatRoomType $type, string $roomKey): ChatRoom
    {
        return new ChatRoom(
            id: 3,
            type: $type,
            roomKey: $roomKey,
            name: 'テストルーム',
            guildId: $type === ChatRoomType::GUILD ? 7 : null,
            memberCount: 2,
            createdAt: '2026-09-05 00:00:00',
        );
    }

    private function makeMember(ChatRoomRole $role): ChatRoomMember
    {
        return new ChatRoomMember(
            id: 1,
            chatRoomId: 3,
            playerId: 100,
            playerName: 'たろう',
            role: $role,
            joinedAt: '2026-09-05 00:00:00',
        );
    }
}
