<?php

namespace Tests\Feature\Notification;

use App\Domain\Notification\Jobs\SendGuildNotificationJob;
use App\Domain\Notification\Jobs\SendNotificationJob;
use App\Models\Sys\SysFriendApply;
use App\Models\Sys\SysPlayer;
use Illuminate\Support\Facades\Event;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Events\GuildNotificationBroadcast;
use NexusNotification\Events\NotificationCreated;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * フレンド・ギルドの操作から、相手の通知一覧に届くまでを通しで検証する
 *
 * 通知の受け手は送り手と別のシャードにいることがある。
 * 送り手のシャードへ書くと受け手の一覧に出てこないため、シャードが分かれる組み合わせで確かめる。
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_friend_apply_is_notified_to_receiver_on_another_shard(): void
    {
        Event::fake([NotificationCreated::class]);

        [$sender, $receiver] = $this->signUpPlayersOnDifferentShards();

        $this->withHeaders($this->authHeaders($sender['token']))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver['player']->my_id])
            ->assertOk();

        $apply = SysFriendApply::where('sender_sys_player_id', $sender['player']->id)->firstOrFail();

        $notification = $this->singleNotification($receiver['token']);
        $this->assertSame(NotificationType::FRIEND_APPLY_RECEIVED->value, $notification['type']);
        $this->assertSame($apply->id, $notification['payload']['sys_friend_apply_id']);
        $this->assertSame($sender['player']->id, $notification['payload']['sender_sys_player_id']);

        // 送り手には届かない
        $this->assertSame([], $this->notificationList($sender['token']));

        // Reverb へのブロードキャストは受け手のチャンネルへ積まれる
        Event::assertDispatched(
            NotificationCreated::class,
            fn (NotificationCreated $event) => $event->notification->getPlayerId() === $receiver['player']->id
        );
    }

    #[Test]
    public function test_friend_apply_accept_is_notified_to_sender(): void
    {
        [$sender, $receiver] = $this->signUpPlayersOnDifferentShards();

        $this->withHeaders($this->authHeaders($sender['token']))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver['player']->my_id])
            ->assertOk();
        $apply = SysFriendApply::where('sender_sys_player_id', $sender['player']->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($receiver['token']))
            ->postJson('/api/friend/apply/accept', ['sys_friend_apply_id' => $apply->id])
            ->assertOk();

        $notification = $this->singleNotification($sender['token']);
        $this->assertSame(NotificationType::FRIEND_APPLY_ACCEPTED->value, $notification['type']);
        $this->assertSame($apply->id, $notification['payload']['sys_friend_apply_id']);
    }

    #[Test]
    public function test_failed_friend_apply_sends_no_notification(): void
    {
        [$sender, $receiver] = $this->signUpPlayersOnDifferentShards();

        $this->withHeaders($this->authHeaders($sender['token']))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver['player']->my_id])
            ->assertOk();

        // 重複申請は業務エラーになり、2通目の通知は送られない
        $this->withHeaders($this->authHeaders($sender['token']))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver['player']->my_id])
            ->assertStatus(299);

        $this->assertCount(1, $this->notificationList($receiver['token']));
    }

    #[Test]
    public function test_guild_apply_flow_notifies_master_and_applicant(): void
    {
        Event::fake([GuildNotificationBroadcast::class]);

        [$master, $applicant] = $this->signUpPlayersOnDifferentShards();

        $sysGuildId = $this->withHeaders($this->authHeaders($master['token']))
            ->postJson('/api/guild/create', ['name' => 'Notify Guild '.uniqid(), 'description' => 'test'])
            ->assertOk()
            ->json('sys_guild_id');

        $sysGuildApplyId = $this->withHeaders($this->authHeaders($applicant['token']))
            ->postJson('/api/guild/apply/send', ['sys_guild_id' => $sysGuildId])
            ->assertOk()
            ->json('sys_guild_apply_id');

        $received = $this->singleNotification($master['token']);
        $this->assertSame(NotificationType::GUILD_APPLY_RECEIVED->value, $received['type']);
        $this->assertSame($sysGuildApplyId, $received['payload']['sys_guild_apply_id']);
        $this->assertSame($applicant['player']->id, $received['payload']['applicant_sys_player_id']);

        $this->withHeaders($this->authHeaders($master['token']))
            ->postJson('/api/guild/apply/accept', ['sys_guild_apply_id' => $sysGuildApplyId])
            ->assertOk();

        $accepted = $this->singleNotification($applicant['token']);
        $this->assertSame(NotificationType::GUILD_APPLY_ACCEPTED->value, $accepted['type']);
        $this->assertSame($sysGuildId, $accepted['payload']['sys_guild_id']);

        // ギルド全員へ加入が流れる
        Event::assertDispatched(
            GuildNotificationBroadcast::class,
            fn (GuildNotificationBroadcast $event) => $event->guildId === $sysGuildId
                && $event->notification->getType() === NotificationType::GUILD_MEMBER_JOINED
        );

        $this->withHeaders($this->authHeaders($applicant['token']))
            ->postJson('/api/guild/leave')
            ->assertOk();

        Event::assertDispatched(
            GuildNotificationBroadcast::class,
            fn (GuildNotificationBroadcast $event) => $event->guildId === $sysGuildId
                && $event->notification->getType() === NotificationType::GUILD_MEMBER_LEFT
        );
    }

    #[Test]
    public function test_guild_apply_reject_is_notified_to_applicant(): void
    {
        [$master, $applicant] = $this->signUpPlayersOnDifferentShards();

        $sysGuildId = $this->withHeaders($this->authHeaders($master['token']))
            ->postJson('/api/guild/create', ['name' => 'Reject Guild '.uniqid(), 'description' => 'test'])
            ->assertOk()
            ->json('sys_guild_id');

        $sysGuildApplyId = $this->withHeaders($this->authHeaders($applicant['token']))
            ->postJson('/api/guild/apply/send', ['sys_guild_id' => $sysGuildId])
            ->assertOk()
            ->json('sys_guild_apply_id');

        $this->withHeaders($this->authHeaders($master['token']))
            ->postJson('/api/guild/apply/reject', ['sys_guild_apply_id' => $sysGuildApplyId])
            ->assertOk();

        $rejected = $this->singleNotification($applicant['token']);
        $this->assertSame(NotificationType::GUILD_APPLY_REJECTED->value, $rejected['type']);
        $this->assertSame($sysGuildApplyId, $rejected['payload']['sys_guild_apply_id']);
    }

    #[Test]
    public function test_jobs_run_after_commit(): void
    {
        // 送り手のトランザクションがロールバックされたら通知を送らないため
        $this->assertTrue((new SendNotificationJob(1, NotificationType::FRIEND_APPLY_RECEIVED, 't', 'b'))->afterCommit);
        $this->assertTrue((new SendGuildNotificationJob(1, NotificationType::GUILD_MEMBER_LEFT, 't', 'b'))->afterCommit);
    }

    /**
     * 割り当てシャードが異なる2人のプレイヤーを作る
     *
     * @return array{0: array{player: SysPlayer, token: string}, 1: array{player: SysPlayer, token: string}}
     */
    private function signUpPlayersOnDifferentShards(): array
    {
        $first = $this->signUpPlayer();
        $firstConnection = $this->playerConnection($first['player']->id);

        for ($i = 0; $i < 20; $i++) {
            $second = $this->signUpPlayer();
            if ($this->playerConnection($second['player']->id) !== $firstConnection) {
                return [$first, $second];
            }
        }

        $this->fail('別のシャードに割り当てられるプレイヤーを作れなかった');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notificationList(string $token): array
    {
        return $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/notification/list')
            ->assertOk()
            ->json('notification_list');
    }

    /**
     * @return array<string, mixed>
     */
    private function singleNotification(string $token): array
    {
        $list = $this->notificationList($token);
        $this->assertCount(1, $list);

        return $list[0];
    }
}
