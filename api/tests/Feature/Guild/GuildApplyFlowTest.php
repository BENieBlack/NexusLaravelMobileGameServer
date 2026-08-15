<?php

namespace Tests\Feature\Guild;

use App\Models\Sys\SysGuildApply;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ギルド加入申請のエンドポイントを通しで検証する
 *
 * 申請→一覧→承認／却下、および脱退の流れをカバーする。
 */
class GuildApplyFlowTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_apply_send_creates_apply(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();

        $guildId = $this->createGuild($masterToken);

        $response = $this->withHeaders($this->authHeaders($applicantToken))
            ->postJson('/api/guild/apply/send', ['guild_id' => $guildId]);

        $response->assertOk();

        $this->assertDatabaseHas('sys_guild_apply', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_apply_list_returns_pending_applies(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['token' => $applicantToken] = $this->signUpPlayer();

        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);

        $response = $this->withHeaders($this->authHeaders($masterToken))
            ->getJson('/api/guild/apply/list?guild_id='.$guildId);

        $response->assertOk();
        $this->assertNotEmpty($response->json());
    }

    #[Test]
    public function test_apply_accept_adds_member(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();

        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);

        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($masterToken))
            ->postJson('/api/guild/apply/accept', ['apply_id' => $apply->id])
            ->assertOk();

        $this->assertDatabaseHas('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_apply_reject_does_not_add_member(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();

        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);

        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($masterToken))
            ->postJson('/api/guild/apply/reject', ['apply_id' => $apply->id])
            ->assertOk();

        $this->assertDatabaseMissing('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_guild_leave_removes_member(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();

        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);

        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();
        $this->withHeaders($this->authHeaders($masterToken))
            ->postJson('/api/guild/apply/accept', ['apply_id' => $apply->id])
            ->assertOk();

        $this->withHeaders($this->authHeaders($applicantToken))
            ->postJson('/api/guild/leave', [])
            ->assertOk();

        $this->assertDatabaseMissing('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/guild/apply/send', ['guild_id' => 1])->assertStatus(401);
        $this->postJson('/api/guild/leave', [])->assertStatus(401);
    }

    private function createGuild(string $token): int
    {
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/guild/create', [
                'name' => 'Guild '.uniqid(),
                'description' => 'apply flow test',
            ]);
        $response->assertOk();

        return (int) $response->json('guild_id');
    }

    private function sendApply(string $token, int $guildId): void
    {
        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/guild/apply/send', ['guild_id' => $guildId])
            ->assertOk();
    }
}
