<?php

namespace Tests\Feature\Guild;

use App\Exceptions\GameErrorCode;
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
            ->postJson('/api/guild/apply/send', ['sys_guild_id' => $guildId]);

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
            ->getJson('/api/guild/apply/list?sys_guild_id='.$guildId);

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
            ->postJson('/api/guild/apply/accept', ['sys_guild_apply_id' => $apply->id])
            ->assertOk();

        $this->assertDatabaseHas('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_apply_accept_rejects_player_already_in_another_guild(): void
    {
        // 2つのギルドに申請しておき、片方で承認された後にもう片方でも承認されるケース
        ['token' => $masterTokenA] = $this->signUpPlayer();
        ['token' => $masterTokenB] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();

        $guildIdA = $this->createGuild($masterTokenA);
        $guildIdB = $this->createGuild($masterTokenB);
        $this->sendApply($applicantToken, $guildIdA);
        $this->sendApply($applicantToken, $guildIdB);

        $applyA = SysGuildApply::where('sys_player_id', $applicant->id)->where('sys_guild_id', $guildIdA)->firstOrFail();
        $applyB = SysGuildApply::where('sys_player_id', $applicant->id)->where('sys_guild_id', $guildIdB)->firstOrFail();

        $this->withHeaders($this->authHeaders($masterTokenA))
            ->postJson('/api/guild/apply/accept', ['sys_guild_apply_id' => $applyA->id])
            ->assertOk();

        $response = $this->withHeaders($this->authHeaders($masterTokenB))
            ->postJson('/api/guild/apply/accept', ['sys_guild_apply_id' => $applyB->id]);

        $this->assertSame(GameErrorCode::PLAYER_ALREADY_IN_GUILD, $response->json('error_code'));
        $this->assertDatabaseMissing('sys_guild_member', [
            'sys_guild_id' => $guildIdB,
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
            ->postJson('/api/guild/apply/reject', ['sys_guild_apply_id' => $apply->id])
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
            ->postJson('/api/guild/apply/accept', ['sys_guild_apply_id' => $apply->id])
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
        $this->postJson('/api/guild/apply/send', ['sys_guild_id' => 1])->assertStatus(401);
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

        return (int) $response->json('sys_guild_id');
    }

    private function sendApply(string $token, int $guildId): void
    {
        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/guild/apply/send', ['sys_guild_id' => $guildId])
            ->assertOk();
    }
}
