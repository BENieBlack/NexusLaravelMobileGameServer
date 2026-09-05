<?php

namespace NexusChat\Tests\Unit\Constants;

use NexusChat\Constants\ChatRoomMemberLimit;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Constants\ChatRoomType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * チャットの定数のテスト
 *
 * ロールごとの権限はグループチャットの荒らし対策そのものなので、
 * 「誰が何をできるか」を表として固定する。
 */
class ChatRoomRoleTest extends TestCase
{
    #[Test]
    public function オーナーは全ての権限を持つ(): void
    {
        $this->assertTrue(ChatRoomRole::OWNER->canInvite());
        $this->assertTrue(ChatRoomRole::OWNER->canKick());
        $this->assertTrue(ChatRoomRole::OWNER->canManageRoles());
    }

    #[Test]
    public function 管理者は招待とキックだけできる(): void
    {
        $this->assertTrue(ChatRoomRole::ADMIN->canInvite());
        $this->assertTrue(ChatRoomRole::ADMIN->canKick());
        $this->assertFalse(ChatRoomRole::ADMIN->canManageRoles());
    }

    #[Test]
    public function 一般メンバーは何も管理できない(): void
    {
        $this->assertFalse(ChatRoomRole::MEMBER->canInvite());
        $this->assertFalse(ChatRoomRole::MEMBER->canKick());
        $this->assertFalse(ChatRoomRole::MEMBER->canManageRoles());
    }

    #[Test]
    public function ロールの文字列表現はデータベースに入る値と一致する(): void
    {
        $this->assertSame('owner', ChatRoomRole::OWNER->value);
        $this->assertSame('admin', ChatRoomRole::ADMIN->value);
        $this->assertSame('member', ChatRoomRole::MEMBER->value);
    }

    #[Test]
    public function ルーム種別の文字列表現はデータベースに入る値と一致する(): void
    {
        $this->assertSame('friend', ChatRoomType::FRIEND->value);
        $this->assertSame('guild', ChatRoomType::GUILD->value);
        $this->assertSame('group', ChatRoomType::GROUP->value);
    }

    #[Test]
    public function 人数上限は種別ごとに決まっている(): void
    {
        $this->assertSame(2, ChatRoomMemberLimit::FRIEND);
        $this->assertSame(20, ChatRoomMemberLimit::GROUP);
        // ギルドは人数管理をギルド側に任せるため上限を設けない
        $this->assertSame(PHP_INT_MAX, ChatRoomMemberLimit::GUILD);
    }
}
