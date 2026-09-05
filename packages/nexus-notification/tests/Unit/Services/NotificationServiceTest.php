<?php

namespace NexusNotification\Tests\Unit\Services;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Contracts\NotificationDispatcherInterface;
use NexusNotification\Contracts\NotificationRepositoryInterface;
use NexusNotification\DataTransferObjects\Notification;
use NexusNotification\Events\NotificationCreated;
use NexusNotification\Services\NotificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NotificationServiceのユニットテスト
 *
 * Dispatcher（リアルタイム配送）は任意なので、
 * 設定されている場合と居ない場合の両方を固定する。
 */
class NotificationServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private NotificationRepositoryInterface $repository;

    /** @var list<NotificationCreated> 発火したイベント */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        // NotificationService は event() ヘルパを直接呼ぶ。
        // Laravelアプリを立ち上げないユニットテストでは 'events' が解決できないため、
        // 最小限のコンテナを差し込む
        $this->dispatchedEvents = [];
        $container = new Container;
        $dispatcher = new Dispatcher($container);
        $dispatcher->listen(NotificationCreated::class, function (NotificationCreated $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $container->instance('events', $dispatcher);
        Container::setInstance($container);

        $this->repository = Mockery::mock(NotificationRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function 通知を永続化して返す(): void
    {
        $notification = $this->makeNotification();

        $this->repository->shouldReceive('insert')
            ->with(1, 'friend_apply_received', 'フレンド申請', 'たろうさんから申請が届きました', ['friend_apply_id' => 9])
            ->once()
            ->andReturn($notification);

        $service = new NotificationService($this->repository);

        $result = $service->notify(
            playerId: 1,
            type: NotificationType::FRIEND_APPLY_RECEIVED,
            title: 'フレンド申請',
            body: 'たろうさんから申請が届きました',
            payload: ['friend_apply_id' => 9],
        );

        $this->assertSame($notification, $result);
    }

    #[Test]
    public function 通知を作るとドメインイベントが飛ぶ(): void
    {
        $notification = $this->makeNotification();
        $this->repository->shouldReceive('insert')->andReturn($notification);

        (new NotificationService($this->repository))->notify(
            playerId: 1,
            type: NotificationType::MAILBOX_RECEIVED,
            title: 'メールが届きました',
            body: '受け取ってください',
        );

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertSame($notification, $this->dispatchedEvents[0]->notification);
    }

    #[Test]
    public function 配送先が設定されていればリアルタイム配送する(): void
    {
        $notification = $this->makeNotification();
        $this->repository->shouldReceive('insert')->andReturn($notification);

        $dispatcher = Mockery::mock(NotificationDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->with($notification)->once();

        (new NotificationService($this->repository, $dispatcher))->notify(
            playerId: 1,
            type: NotificationType::MISSION_COMPLETED,
            title: 'ミッション達成',
            body: 'おめでとう',
        );
    }

    #[Test]
    public function 配送先が無くても通知は作れる(): void
    {
        // 配送先の設定が無い環境（テスト・バッチ等）でも落とさない
        $notification = $this->makeNotification();
        $this->repository->shouldReceive('insert')->andReturn($notification);

        $result = (new NotificationService($this->repository))->notify(
            playerId: 1,
            type: NotificationType::SYSTEM_ANNOUNCEMENT,
            title: 'お知らせ',
            body: 'メンテナンスのお知らせ',
        );

        $this->assertSame($notification, $result);
    }

    #[Test]
    public function 通知一覧を取得できる(): void
    {
        $notifications = [$this->makeNotification()];
        $this->repository->shouldReceive('selectByPlayerId')->with(1, false)->andReturn($notifications);

        $service = new NotificationService($this->repository);

        $this->assertSame($notifications, $service->findByPlayer(1));
    }

    #[Test]
    public function 未読のみの取得を指定できる(): void
    {
        $notifications = [$this->makeNotification()];
        $this->repository->shouldReceive('selectByPlayerId')->with(1, true)->andReturn($notifications);

        $service = new NotificationService($this->repository);

        $this->assertSame($notifications, $service->findByPlayer(1, true));
    }

    #[Test]
    public function 自分の通知は既読にできる(): void
    {
        $this->repository->shouldReceive('selectById')->with(5)->andReturn($this->makeNotification(id: 5, playerId: 1));
        $this->repository->shouldReceive('markAsRead')->with(5)->once();

        (new NotificationService($this->repository))->markAsRead(5, 1);
    }

    #[Test]
    public function 他人の通知は既読にできない(): void
    {
        // IDを総当たりされても他人の通知に触れない
        $this->repository->shouldReceive('selectById')->andReturn($this->makeNotification(playerId: 999));
        $this->repository->shouldNotReceive('markAsRead');

        (new NotificationService($this->repository))->markAsRead(5, 1);

        $this->assertTrue(true);
    }

    #[Test]
    public function 存在しない通知の既読は何もしない(): void
    {
        $this->repository->shouldReceive('selectById')->andReturn(null);
        $this->repository->shouldNotReceive('markAsRead');

        (new NotificationService($this->repository))->markAsRead(5, 1);

        $this->assertTrue(true);
    }

    #[Test]
    public function 全件既読はリポジトリへ委譲する(): void
    {
        $this->repository->shouldReceive('markAllAsRead')->with(1)->once();

        (new NotificationService($this->repository))->markAllAsRead(1);
    }

    #[Test]
    public function 未読件数を取得できる(): void
    {
        $this->repository->shouldReceive('countUnread')->with(1)->andReturn(3);

        $this->assertSame(3, (new NotificationService($this->repository))->countUnread(1));
    }

    private function makeNotification(int $id = 1, int $playerId = 1): Notification
    {
        return new Notification(
            id: $id,
            playerId: $playerId,
            type: NotificationType::FRIEND_APPLY_RECEIVED,
            title: 'フレンド申請',
            body: 'たろうさんから申請が届きました',
            payload: ['friend_apply_id' => 9],
            isRead: false,
            readAt: null,
            createdAt: '2026-09-05 00:00:00',
        );
    }
}
