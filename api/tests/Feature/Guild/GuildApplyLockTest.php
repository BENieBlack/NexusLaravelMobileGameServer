<?php

namespace Tests\Feature\Guild;

use App\Exceptions\GameErrorCode;
use App\Models\Sys\SysGuildApply;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ギルド加入申請の承認・却下の排他（行ロック）を検証する
 *
 * 同時実行そのものはテストで再現できないので、次の 2 点で守りを固定する。
 *  - ロックが SELECT ... FOR UPDATE として発行されること（SQL を検査）
 *  - ロックの後の判定（満員・二重所属・承認済み／却下済み）が、最新の値で効くこと
 */
class GuildApplyLockTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_accept_locks_apply_guild_members_and_applicant_in_order(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();
        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);
        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();

        $locks = $this->collectLockTables(function () use ($masterToken, $apply): void {
            $this->acceptApply($masterToken, $apply->id)->assertOk();
        });

        // 申請 → ギルド → メンバー数 → 申請者のプレイヤー行 → 申請者の所属、の順
        // （順序を固定してデッドロックを避ける。architecture.md 7節）
        $this->assertSame(
            ['sys_guild_apply', 'sys_guild', 'sys_guild_member', 'sys_player', 'sys_guild_member'],
            $locks
        );
    }

    #[Test]
    public function test_create_locks_creator_player_row_before_checking_membership(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();

        $locks = $this->collectLockTables(function () use ($masterToken): void {
            $this->createGuild($masterToken);
        });

        $this->assertSame(['sys_player', 'sys_guild_member'], $locks);

        // 所属済みの人は作れない（最新の所属を見て止まる）
        $response = $this->withHeaders($this->authHeaders($masterToken))
            ->postJson('/api/guild/create', ['name' => 'Guild '.uniqid(), 'description' => 'x']);
        $this->assertSame(GameErrorCode::PLAYER_ALREADY_IN_GUILD, $response->json('error_code'));
    }

    #[Test]
    public function test_reject_locks_only_the_apply_row(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();
        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);
        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();

        $locks = $this->collectLockTables(function () use ($masterToken, $apply): void {
            $this->rejectApply($masterToken, $apply->id)->assertOk();
        });

        $this->assertSame(['sys_guild_apply'], $locks);
    }

    #[Test]
    public function test_accept_fails_with_guild_full_after_reaching_the_limit(): void
    {
        // 上限 2 人（マスター + 1 人）。1 人目は入れるが、2 人目は満員
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $first, 'token' => $firstToken] = $this->signUpPlayer();
        ['player' => $second, 'token' => $secondToken] = $this->signUpPlayer();
        $guildId = $this->createGuild($masterToken);
        DB::connection('sys')->table('sys_guild')->where('id', $guildId)->update(['max_members' => 2]);

        $this->sendApply($firstToken, $guildId);
        $this->sendApply($secondToken, $guildId);
        $firstApply = SysGuildApply::where('sys_player_id', $first->id)->firstOrFail();
        $secondApply = SysGuildApply::where('sys_player_id', $second->id)->firstOrFail();

        $this->acceptApply($masterToken, $firstApply->id)->assertOk();
        $response = $this->acceptApply($masterToken, $secondApply->id);

        $this->assertSame(GameErrorCode::GUILD_FULL, $response->json('error_code'));
        $this->assertDatabaseMissing('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $second->id,
        ], 'sys');
        $this->assertDatabaseHas('sys_guild_apply', [
            'id' => $secondApply->id,
            'status' => 'applied',
        ], 'sys');
    }

    #[Test]
    public function test_reject_after_accept_is_a_business_error(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();
        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);
        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();

        $this->acceptApply($masterToken, $apply->id)->assertOk();
        $response = $this->rejectApply($masterToken, $apply->id);

        $this->assertSame(GameErrorCode::GUILD_INVALID_STATUS, $response->json('error_code'));
        $this->assertDatabaseHas('sys_guild_apply', ['id' => $apply->id, 'status' => 'accepted'], 'sys');
        $this->assertDatabaseHas('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_accept_after_reject_is_a_business_error(): void
    {
        ['token' => $masterToken] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();
        $guildId = $this->createGuild($masterToken);
        $this->sendApply($applicantToken, $guildId);
        $apply = SysGuildApply::where('sys_player_id', $applicant->id)->firstOrFail();

        $this->rejectApply($masterToken, $apply->id)->assertOk();
        $response = $this->acceptApply($masterToken, $apply->id);

        $this->assertSame(GameErrorCode::GUILD_INVALID_STATUS, $response->json('error_code'));
        $this->assertDatabaseHas('sys_guild_apply', ['id' => $apply->id, 'status' => 'rejected'], 'sys');
        $this->assertDatabaseMissing('sys_guild_member', [
            'sys_guild_id' => $guildId,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    #[Test]
    public function test_accept_rechecks_applicant_membership_under_lock(): void
    {
        // 申請後に別ギルドへ入った人を、ロックを取った後の再確認で止める
        ['token' => $masterTokenA] = $this->signUpPlayer();
        ['token' => $masterTokenB] = $this->signUpPlayer();
        ['player' => $applicant, 'token' => $applicantToken] = $this->signUpPlayer();
        $guildIdA = $this->createGuild($masterTokenA);
        $guildIdB = $this->createGuild($masterTokenB);
        $this->sendApply($applicantToken, $guildIdA);
        $this->sendApply($applicantToken, $guildIdB);
        $applyA = SysGuildApply::where('sys_player_id', $applicant->id)->where('sys_guild_id', $guildIdA)->firstOrFail();
        $applyB = SysGuildApply::where('sys_player_id', $applicant->id)->where('sys_guild_id', $guildIdB)->firstOrFail();

        $this->acceptApply($masterTokenA, $applyA->id)->assertOk();

        $response = null;
        $locks = $this->collectLockTables(function () use ($masterTokenB, $applyB, &$response): void {
            $response = $this->acceptApply($masterTokenB, $applyB->id);
        });

        $this->assertSame(GameErrorCode::PLAYER_ALREADY_IN_GUILD, $response?->json('error_code'));
        // 二重所属の確認は、申請者のプレイヤー行をロックしてから行っている
        $this->assertContains('sys_player', $locks);
        $this->assertDatabaseMissing('sys_guild_member', [
            'sys_guild_id' => $guildIdB,
            'sys_player_id' => $applicant->id,
        ], 'sys');
    }

    /**
     * 処理中に発行された FOR UPDATE 付きの SELECT の、対象テーブルを発行順に返す
     *
     * @return list<string>
     */
    private function collectLockTables(\Closure $callback): array
    {
        $tables = [];
        DB::listen(function ($query) use (&$tables): void {
            if (preg_match('/^\s*select\b.*\bfrom\s+[`"]?(\w+)[`"]?.*\bfor update\s*$/is', $query->sql, $m) === 1) {
                $tables[] = $m[1];
            }
        });

        $callback();

        return $tables;
    }

    private function acceptApply(string $token, int $applyId): TestResponse
    {
        return $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/guild/apply/accept', ['sys_guild_apply_id' => $applyId]);
    }

    private function rejectApply(string $token, int $applyId): TestResponse
    {
        return $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/guild/apply/reject', ['sys_guild_apply_id' => $applyId]);
    }

    private function createGuild(string $token): int
    {
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/guild/create', [
                'name' => 'Guild '.uniqid(),
                'description' => 'apply lock test',
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
