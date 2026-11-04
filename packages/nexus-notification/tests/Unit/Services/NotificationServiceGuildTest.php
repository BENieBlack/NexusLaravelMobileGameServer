<?php

namespace NexusNotification\Tests\Unit\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Contracts\NotificationRepositoryInterface;
use NexusNotification\Events\GuildNotificationBroadcast;
use NexusNotification\Services\NotificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NotificationService::notifyGuild() のユニットテスト
 *
 * ギルド全員向け通知のブロードキャストフローを固定する。
 * DBへの保存は行わず、GuildNotificationBroadcast イベントが発火することを確認する。
 */
class NotificationServiceGuildTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private NotificationRepositoryInterface $repository;

    private Dispatcher $events;

    /** @var list<GuildNotificationBroadcast> 発火したギルドブロードキャストイベント */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        // イベントは NotificationService が受け取った Dispatcher へ発行される。
        // Laravelアプリを立ち上げないので、最小限のコンテナで本物の Dispatcher を作る
        $this->dispatchedEvents = [];
        $container = new Container;
        $dispatcher = new Dispatcher($container);
        $dispatcher->listen(GuildNotificationBroadcast::class, function (GuildNotificationBroadcast $event): void {
            $this->dispatchedEvents[] = $event;
        });
        $container->instance('events', $dispatcher);
        $this->events = $dispatcher;

        // GuildNotificationBroadcast は ShouldBroadcast を実装しているため、
        // 発行時に Broadcasting\Factory の解決が走る。
        // テストではブロードキャスト自体は不要なので何もしないスタブを差し込む。
        $broadcastManager = Mockery::mock(BroadcastFactory::class);
        $broadcastManager->shouldReceive('queue')->andReturnNull();
        $container->instance(BroadcastFactory::class, $broadcastManager);
        $container->instance('broadcast', $broadcastManager);

        $this->repository = Mockery::mock(NotificationRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function notify_guild_は_guild_notification_broadcastイベントを発火する(): void
    {
        $service = new NotificationService($this->repository, null, $this->events);

        $service->notifyGuild(
            guildId: 1,
            type: NotificationType::GUILD_MEMBER_JOINED,
            title: 'メンバーが加入しました',
            body: 'たろうさんがギルドに加入しました',
        );

        $this->assertCount(1, $this->dispatchedEvents);
    }

    #[Test]
    public function notify_guild_イベントのguild_idが正しい(): void
    {
        $service = new NotificationService($this->repository, null, $this->events);

        $service->notifyGuild(
            guildId: 42,
            type: NotificationType::GUILD_MEMBER_LEFT,
            title: 'メンバーが脱退しました',
            body: 'たろうさんがギルドから脱退しました',
        );

        $this->assertSame(42, $this->dispatchedEvents[0]->guildId);
    }

    #[Test]
    public function notify_guild_イベントのnotification_typeが正しい(): void
    {
        $service = new NotificationService($this->repository, null, $this->events);

        $service->notifyGuild(
            guildId: 1,
            type: NotificationType::GUILD_LEVEL_UP,
            title: 'ギルドレベルアップ',
            body: 'ギルドがレベル2になりました',
        );

        $event = $this->dispatchedEvents[0];
        $this->assertSame(NotificationType::GUILD_LEVEL_UP, $event->notification->getType());
    }

    #[Test]
    public function notify_guild_イベントのtitleとbodyが正しい(): void
    {
        $service = new NotificationService($this->repository, null, $this->events);

        $service->notifyGuild(
            guildId: 1,
            type: NotificationType::GUILD_MEMBER_JOINED,
            title: 'メンバーが加入しました',
            body: 'たろうさんがギルドに加入しました',
        );

        $notification = $this->dispatchedEvents[0]->notification;
        $this->assertSame('メンバーが加入しました', $notification->getTitle());
        $this->assertSame('たろうさんがギルドに加入しました', $notification->getBody());
    }

    #[Test]
    public function notify_guild_payloadを渡せる(): void
    {
        $service = new NotificationService($this->repository, null, $this->events);

        $service->notifyGuild(
            guildId: 1,
            type: NotificationType::GUILD_MEMBER_JOINED,
            title: 'メンバーが加入しました',
            body: 'たろうさんがギルドに加入しました',
            payload: ['sys_player_id' => 99],
        );

        $this->assertSame(['sys_player_id' => 99], $this->dispatchedEvents[0]->notification->getPayload());
    }

    #[Test]
    public function notify_guild_d_bには保存しない(): void
    {
        // repository の insert が呼ばれないことを確認する
        $this->repository->shouldNotReceive('insert');

        (new NotificationService($this->repository, null, $this->events))->notifyGuild(
            guildId: 1,
            type: NotificationType::GUILD_MEMBER_JOINED,
            title: 'メンバーが加入しました',
            body: 'たろうさんがギルドに加入しました',
        );

        $this->assertTrue(true);
    }
}
