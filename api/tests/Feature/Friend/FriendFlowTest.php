<?php

namespace Tests\Feature\Friend;

use App\Models\Sys\SysFriendApply;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * フレンド機能のエンドポイントを通しで検証する
 *
 * 申請→一覧→承認→フレンド一覧→削除、および却下の流れをカバーする。
 */
class FriendFlowTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_apply_send_creates_apply(): void
    {
        ['player' => $sender, 'token' => $token] = $this->signUpPlayer();
        ['player' => $receiver] = $this->signUpPlayer();

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver->my_id]);

        $response->assertOk();

        $this->assertDatabaseHas('sys_friend_apply', [
            'sender_sys_player_id' => $sender->id,
            'receiver_sys_player_id' => $receiver->id,
            'status' => SysFriendApply::STATUS_APPLIED,
        ], 'sys');
    }

    #[Test]
    public function test_apply_list_returns_received_applies(): void
    {
        ['player' => $sender, 'token' => $senderToken] = $this->signUpPlayer();
        ['player' => $receiver, 'token' => $receiverToken] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($senderToken))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver->my_id])
            ->assertOk();

        $response = $this->withHeaders($this->authHeaders($receiverToken))
            ->getJson('/api/friend/apply/list');

        $response->assertOk();
        $response->assertJsonStructure([
            'applies' => [
                '*' => [
                    'sys_friend_apply_id',
                    'sender_my_id',
                    'receiver_my_id',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        $applies = $response->json('applies');
        $this->assertCount(1, $applies);
        $this->assertSame($sender->my_id, $applies[0]['sender_my_id']);
        $this->assertSame($receiver->my_id, $applies[0]['receiver_my_id']);
        $this->assertSame(SysFriendApply::STATUS_APPLIED, $applies[0]['status']);
    }

    #[Test]
    public function test_apply_accept_makes_them_friends(): void
    {
        ['player' => $sender, 'token' => $senderToken] = $this->signUpPlayer();
        ['player' => $receiver, 'token' => $receiverToken] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($senderToken))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver->my_id])
            ->assertOk();

        $apply = SysFriendApply::where('sender_sys_player_id', $sender->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($receiverToken))
            ->postJson('/api/friend/apply/accept', ['sys_friend_apply_id' => $apply->id])
            ->assertOk();

        $this->assertDatabaseHas('sys_friend_apply', [
            'id' => $apply->id,
            'status' => SysFriendApply::STATUS_ACCEPTED,
        ], 'sys');
    }

    #[Test]
    public function test_apply_reject_marks_as_rejected(): void
    {
        ['player' => $sender, 'token' => $senderToken] = $this->signUpPlayer();
        ['player' => $receiver, 'token' => $receiverToken] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($senderToken))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver->my_id])
            ->assertOk();

        $apply = SysFriendApply::where('sender_sys_player_id', $sender->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($receiverToken))
            ->postJson('/api/friend/apply/reject', ['sys_friend_apply_id' => $apply->id])
            ->assertOk();

        $this->assertDatabaseHas('sys_friend_apply', [
            'id' => $apply->id,
            'status' => SysFriendApply::STATUS_REJECTED,
        ], 'sys');
    }

    #[Test]
    public function test_friend_list_returns_accepted_friends(): void
    {
        ['player' => $sender, 'token' => $senderToken] = $this->signUpPlayer();
        ['player' => $receiver, 'token' => $receiverToken] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($senderToken))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver->my_id])
            ->assertOk();

        $apply = SysFriendApply::where('sender_sys_player_id', $sender->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($receiverToken))
            ->postJson('/api/friend/apply/accept', ['sys_friend_apply_id' => $apply->id])
            ->assertOk();

        $this->withHeaders($this->authHeaders($senderToken))
            ->getJson('/api/friend/list')
            ->assertOk();
    }

    #[Test]
    public function test_friend_delete_removes_relation(): void
    {
        ['player' => $sender, 'token' => $senderToken] = $this->signUpPlayer();
        ['player' => $receiver, 'token' => $receiverToken] = $this->signUpPlayer();

        $this->withHeaders($this->authHeaders($senderToken))
            ->postJson('/api/friend/apply/send', ['my_id' => $receiver->my_id])
            ->assertOk();

        $apply = SysFriendApply::where('sender_sys_player_id', $sender->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($receiverToken))
            ->postJson('/api/friend/apply/accept', ['sys_friend_apply_id' => $apply->id])
            ->assertOk();

        $this->withHeaders($this->authHeaders($senderToken))
            ->postJson('/api/friend/delete', ['my_id' => $receiver->my_id])
            ->assertOk();

        // sys_friend_apply に is_delete カラムは無いため物理削除される
        $this->assertDatabaseMissing('sys_friend_apply', [
            'id' => $apply->id,
        ], 'sys');
    }

    #[Test]
    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/friend/apply/send', ['my_id' => 'ANY00001'])->assertStatus(401);
        $this->getJson('/api/friend/list')->assertStatus(401);
    }
}
