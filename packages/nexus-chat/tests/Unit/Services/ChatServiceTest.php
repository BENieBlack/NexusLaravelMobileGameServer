<?php

namespace NexusChat\Tests\Unit\Services;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Constants\ChatRoomType;
use NexusChat\Contracts\ChatMessageRepositoryInterface;
use NexusChat\Contracts\ChatRoomMemberRepositoryInterface;
use NexusChat\Contracts\ChatRoomRepositoryInterface;
use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\DataTransferObjects\ChatRoomMember;
use NexusChat\Events\MessageSent;
use NexusChat\Exceptions\ChatException;
use NexusChat\Services\ChatService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ChatServiceのユニットテスト
 *
 * 権限まわり（誰が招待・キック・ロール変更できるか）と、
 * GUILDルームだけメンバーテーブルを見ない分岐を重点的に固定する。
 */
class ChatServiceTest extends TestCase
{
    // Mockeryの期待値をPHPUnitのアサーション数に数えさせる。
    // 戻り値の無いメソッドのテストが「アサーション無し」扱いにならない
    use MockeryPHPUnitIntegration;

    private ChatRoomRepositoryInterface $roomRepository;

    private ChatMessageRepositoryInterface $messageRepository;

    private ChatRoomMemberRepositoryInterface $memberRepository;

    private ChatService $service;

    /** @var list<object> 発火したイベント */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        // ChatService は event() ヘルパを直接呼ぶ。Laravelアプリを立ち上げない
        // ユニットテストでは 'events' が解決できずに落ちるため、最小限のコンテナを差し込む
        $this->dispatchedEvents = [];
        $container = new Container;
        $dispatcher = new Dispatcher($container);
        $dispatcher->listen(MessageSent::class, function (MessageSent $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $container->instance('events', $dispatcher);
        Container::setInstance($container);

        $this->roomRepository = Mockery::mock(ChatRoomRepositoryInterface::class);
        $this->messageRepository = Mockery::mock(ChatMessageRepositoryInterface::class);
        $this->memberRepository = Mockery::mock(ChatRoomMemberRepositoryInterface::class);

        $this->service = new ChatService(
            $this->roomRepository,
            $this->messageRepository,
            $this->memberRepository,
        );
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // メッセージ送信
    // =========================================================

    #[Test]
    public function メンバーならメッセージを送信できる(): void
    {
        $room = $this->makeRoom(type: ChatRoomType::GROUP);
        $message = $this->makeMessage();

        $this->roomRepository->shouldReceive('selectById')->with(1)->andReturn($room);
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')
            ->with(1, 100)
            ->andReturn($this->makeMember(playerId: 100));
        $this->messageRepository->shouldReceive('insert')
            ->with(1, 100, 'たろう', 'こんにちは')
            ->andReturn($message);

        $result = $this->service->sendMessage(1, 100, 'たろう', 'こんにちは');

        $this->assertSame($message, $result);
    }

    #[Test]
    public function 送信するとブロードキャスト用のイベントが飛ぶ(): void
    {
        $room = $this->makeRoom(type: ChatRoomType::GROUP);
        $message = $this->makeMessage();

        $this->roomRepository->shouldReceive('selectById')->andReturn($room);
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn($this->makeMember());
        $this->messageRepository->shouldReceive('insert')->andReturn($message);

        $this->service->sendMessage(1, 100, 'たろう', 'こんにちは');

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(MessageSent::class, $this->dispatchedEvents[0]);
        $this->assertSame($message, $this->dispatchedEvents[0]->message);
    }

    #[Test]
    public function ギルドチャットはメンバーテーブルを見ない(): void
    {
        // ギルド加入＝参加なので、メンバーテーブルには行が無い。
        // ここで参加確認をすると全員が弾かれる
        $room = $this->makeRoom(type: ChatRoomType::GUILD);

        $this->roomRepository->shouldReceive('selectById')->andReturn($room);
        $this->memberRepository->shouldNotReceive('selectByRoomAndPlayer');
        $this->messageRepository->shouldReceive('insert')->andReturn($this->makeMessage());

        $this->service->sendMessage(1, 100, 'たろう', 'こんにちは');

        $this->assertCount(1, $this->dispatchedEvents);
    }

    #[Test]
    public function 空文字のメッセージは送信できない(): void
    {
        $this->expectExceptionObject(ChatException::messageEmpty());

        $this->service->sendMessage(1, 100, 'たろう', '   ');
    }

    #[Test]
    public function 上限を超えるメッセージは送信できない(): void
    {
        $this->expectExceptionObject(ChatException::messageTooLong());

        $this->service->sendMessage(1, 100, 'たろう', str_repeat('あ', 501));
    }

    #[Test]
    public function 上限ちょうどのメッセージは送信できる(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom());
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn($this->makeMember());
        $this->messageRepository->shouldReceive('insert')->andReturn($this->makeMessage());

        $this->service->sendMessage(1, 100, 'たろう', str_repeat('あ', 500));

        $this->assertCount(1, $this->dispatchedEvents);
    }

    #[Test]
    public function 存在しないルームへは送信できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn(null);

        $this->expectExceptionObject(ChatException::roomNotFound());

        $this->service->sendMessage(1, 100, 'たろう', 'こんにちは');
    }

    #[Test]
    public function メンバーでなければ送信できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom());
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn(null);

        $this->expectExceptionObject(ChatException::notRoomMember());

        $this->service->sendMessage(1, 100, 'たろう', 'こんにちは');
    }

    // =========================================================
    // メッセージ取得・削除
    // =========================================================

    #[Test]
    public function メンバーならメッセージ履歴を取得できる(): void
    {
        $messages = [$this->makeMessage(), $this->makeMessage(id: 2)];

        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom());
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn($this->makeMember());
        $this->messageRepository->shouldReceive('selectByRoomId')
            ->with(1, 30, null)
            ->andReturn($messages);

        $this->assertSame($messages, $this->service->getMessages(1, 100));
    }

    #[Test]
    public function 履歴取得もメンバーでなければ弾く(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom());
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn(null);

        $this->expectExceptionObject(ChatException::notRoomMember());

        $this->service->getMessages(1, 100);
    }

    #[Test]
    public function 存在しないルームの履歴は取得できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn(null);

        $this->expectExceptionObject(ChatException::roomNotFound());

        $this->service->getMessages(1, 100);
    }

    #[Test]
    public function 自分のメッセージは削除できる(): void
    {
        $this->messageRepository->shouldReceive('selectById')
            ->with(5)
            ->andReturn($this->makeMessage(id: 5, senderPlayerId: 100));
        $this->messageRepository->shouldReceive('softDelete')->with(5)->once();

        $this->service->deleteMessage(5, 100);
    }

    #[Test]
    public function 他人のメッセージは削除できない(): void
    {
        $this->messageRepository->shouldReceive('selectById')
            ->andReturn($this->makeMessage(senderPlayerId: 999));

        $this->expectExceptionObject(ChatException::notMessageOwner());

        $this->service->deleteMessage(5, 100);
    }

    #[Test]
    public function 存在しないメッセージの削除は何もしない(): void
    {
        // 二重に削除リクエストが来ても失敗させない
        $this->messageRepository->shouldReceive('selectById')->andReturn(null);
        $this->messageRepository->shouldNotReceive('softDelete');

        $this->service->deleteMessage(5, 100);

        $this->assertTrue(true);
    }

    // =========================================================
    // ルーム取得
    // =========================================================

    #[Test]
    public function フレンドルームの取得はリポジトリへ委譲する(): void
    {
        $room = $this->makeRoom(type: ChatRoomType::FRIEND);
        $this->roomRepository->shouldReceive('findOrCreateFriendRoom')->with(100, 200)->andReturn($room);

        $this->assertSame($room, $this->service->findOrCreateFriendRoom(100, 200));
    }

    #[Test]
    public function ギルドルームの取得はリポジトリへ委譲する(): void
    {
        $room = $this->makeRoom(type: ChatRoomType::GUILD);
        $this->roomRepository->shouldReceive('findOrCreateGuildRoom')->with(7)->andReturn($room);

        $this->assertSame($room, $this->service->findOrCreateGuildRoom(7));
    }

    #[Test]
    public function 参加中のルーム一覧を取得できる(): void
    {
        $rooms = [$this->makeRoom()];
        $this->roomRepository->shouldReceive('selectRoomsByPlayerId')->with(100)->andReturn($rooms);

        $this->assertSame($rooms, $this->service->getRoomsByPlayer(100));
    }

    // =========================================================
    // グループ作成・招待
    // =========================================================

    #[Test]
    public function グループ作成者は_owne_rとして参加する(): void
    {
        $room = $this->makeRoom(id: 3, type: ChatRoomType::GROUP);

        $this->roomRepository->shouldReceive('createGroupRoom')->with('仲良し')->andReturn($room);
        $this->memberRepository->shouldReceive('insert')
            ->with(3, 100, 'たろう', ChatRoomRole::OWNER)
            ->once()
            ->andReturn($this->makeMember(role: ChatRoomRole::OWNER));
        $this->roomRepository->shouldReceive('updateMemberCount')->with(3, 1)->once();

        $this->assertSame($room, $this->service->createGroupRoom('仲良し', 100, 'たろう'));
    }

    #[Test]
    public function 管理者はメンバーを招待できる(): void
    {
        $member = $this->makeMember(playerId: 200, role: ChatRoomRole::MEMBER);

        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(playerId: 100, role: ChatRoomRole::ADMIN));
        $this->memberRepository->shouldReceive('countByRoomId')->with(1)->andReturn(5);
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 200)->andReturn(null);
        $this->memberRepository->shouldReceive('insert')
            ->with(1, 200, 'じろう', ChatRoomRole::MEMBER)
            ->andReturn($member);
        $this->roomRepository->shouldReceive('updateMemberCount')->with(1, 6)->once();

        $this->assertSame($member, $this->service->inviteToGroup(1, 100, 200, 'じろう'));
    }

    #[Test]
    public function 一般メンバーは招待できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::MEMBER));

        $this->expectExceptionObject(ChatException::noInvitePermission());

        $this->service->inviteToGroup(1, 100, 200, 'じろう');
    }

    #[Test]
    public function グループ以外のルームへは招待できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::FRIEND));

        $this->expectExceptionObject(ChatException::roomNotFound());

        $this->service->inviteToGroup(1, 100, 200, 'じろう');
    }

    #[Test]
    public function ルームに居ない人は招待できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)->andReturn(null);

        $this->expectExceptionObject(ChatException::notRoomMember());

        $this->service->inviteToGroup(1, 100, 200, 'じろう');
    }

    #[Test]
    public function 定員に達していたら招待できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::OWNER));
        $this->memberRepository->shouldReceive('countByRoomId')->andReturn(20);

        $this->expectExceptionObject(ChatException::roomFull());

        $this->service->inviteToGroup(1, 100, 200, 'じろう');
    }

    #[Test]
    public function 既にメンバーの人は招待できない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::OWNER));
        $this->memberRepository->shouldReceive('countByRoomId')->andReturn(5);
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 200)
            ->andReturn($this->makeMember(playerId: 200));

        $this->expectExceptionObject(ChatException::alreadyMember());

        $this->service->inviteToGroup(1, 100, 200, 'じろう');
    }

    // =========================================================
    // キック・退室・ロール変更
    // =========================================================

    #[Test]
    public function オーナーはメンバーをキックできる(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::OWNER));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 200)
            ->andReturn($this->makeMember(playerId: 200, role: ChatRoomRole::MEMBER));
        $this->memberRepository->shouldReceive('delete')->with(1, 200)->once();
        $this->memberRepository->shouldReceive('countByRoomId')->andReturn(4);
        $this->roomRepository->shouldReceive('updateMemberCount')->with(1, 4)->once();

        $this->service->kickFromGroup(1, 100, 200);
    }

    #[Test]
    public function 一般メンバーはキックできない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::MEMBER));

        $this->expectExceptionObject(ChatException::noKickPermission());

        $this->service->kickFromGroup(1, 100, 200);
    }

    #[Test]
    public function オーナーはキックできない(): void
    {
        $this->roomRepository->shouldReceive('selectById')->andReturn($this->makeRoom(type: ChatRoomType::GROUP));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::ADMIN));
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 200)
            ->andReturn($this->makeMember(playerId: 200, role: ChatRoomRole::OWNER));

        $this->expectExceptionObject(ChatException::cannotKickOwner());

        $this->service->kickFromGroup(1, 100, 200);
    }

    #[Test]
    public function 退室するとメンバー数が減る(): void
    {
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember());
        $this->memberRepository->shouldReceive('delete')->with(1, 100)->once();
        $this->memberRepository->shouldReceive('countByRoomId')->andReturn(3);
        $this->roomRepository->shouldReceive('updateMemberCount')->with(1, 3)->once();

        $this->service->leaveGroup(1, 100);
    }

    #[Test]
    public function メンバーでなければ退室できない(): void
    {
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn(null);

        $this->expectExceptionObject(ChatException::notRoomMember());

        $this->service->leaveGroup(1, 100);
    }

    #[Test]
    public function オーナーはロールを変更できる(): void
    {
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::OWNER));
        $this->memberRepository->shouldReceive('updateRole')
            ->with(1, 200, ChatRoomRole::ADMIN)
            ->once();

        $this->service->changeRole(1, 100, 200, ChatRoomRole::ADMIN);
    }

    #[Test]
    public function 管理者はロールを変更できない(): void
    {
        // 招待・キックはできてもロール管理はOWNER専用
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember(role: ChatRoomRole::ADMIN));

        $this->expectExceptionObject(ChatException::noRoleManagePermission());

        $this->service->changeRole(1, 100, 200, ChatRoomRole::ADMIN);
    }

    #[Test]
    public function メンバー一覧はメンバーだけが取得できる(): void
    {
        $members = [$this->makeMember()];
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->with(1, 100)
            ->andReturn($this->makeMember());
        $this->memberRepository->shouldReceive('selectByRoomId')->with(1)->andReturn($members);

        $this->assertSame($members, $this->service->getGroupMembers(1, 100));
    }

    #[Test]
    public function メンバー一覧はメンバー以外には返さない(): void
    {
        $this->memberRepository->shouldReceive('selectByRoomAndPlayer')->andReturn(null);

        $this->expectExceptionObject(ChatException::notRoomMember());

        $this->service->getGroupMembers(1, 100);
    }

    // =========================================================
    // ヘルパ
    // =========================================================

    private function makeRoom(
        int $id = 1,
        ChatRoomType $type = ChatRoomType::GROUP,
    ): ChatRoom {
        return new ChatRoom(
            id: $id,
            type: $type,
            roomKey: (string) $id,
            name: 'テストルーム',
            guildId: $type === ChatRoomType::GUILD ? 7 : null,
            memberCount: 5,
            createdAt: '2026-09-05 00:00:00',
        );
    }

    private function makeMessage(
        int $id = 1,
        int $senderPlayerId = 100,
    ): ChatMessage {
        return new ChatMessage(
            id: $id,
            chatRoomId: 1,
            senderPlayerId: $senderPlayerId,
            senderName: 'たろう',
            body: 'こんにちは',
            isDeleted: false,
            createdAt: '2026-09-05 00:00:00',
        );
    }

    private function makeMember(
        int $playerId = 100,
        ChatRoomRole $role = ChatRoomRole::MEMBER,
    ): ChatRoomMember {
        return new ChatRoomMember(
            id: 1,
            chatRoomId: 1,
            playerId: $playerId,
            playerName: 'たろう',
            role: $role,
            joinedAt: '2026-09-05 00:00:00',
        );
    }
}
