<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Sys\SysGuild;
use App\Models\Sys\SysGuildMember;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * presence-guild.{guildId} チャンネル認証のテスト
 *
 * POST /broadcasting/auth に socket_id と channel_name を渡して
 * ギルドメンバーかどうかで認証が通るか・拒否されるかを固定する。
 */
class GuildChannelAuthTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $token;

    /** setUp で挿入した sys_guild の ID（tearDown で削除） */
    private ?int $guildId = null;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->getId();
        $this->token = $token;
    }

    protected function tearDown(): void
    {
        if ($this->guildId !== null) {
            DB::connection('sys')->table('sys_guild_member')
                ->where('sys_guild_id', $this->guildId)->delete();
            DB::connection('sys')->table('sys_guild')
                ->where('id', $this->guildId)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function ギルドメンバーはチャンネル認証が通る(): void
    {
        $this->guildId = $this->createGuildWithMember($this->sysPlayerId);

        $response = $this->withHeaders($this->authHeaders($this->token))
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '123.456',
                'channel_name' => 'presence-guild.' . $this->guildId,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['auth']);
    }

    #[Test]
    public function 非メンバーはチャンネル認証が拒否される(): void
    {
        // メンバーに追加しないギルドを作る
        $this->guildId = $this->createGuild();

        $response = $this->withHeaders($this->authHeaders($this->token))
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '123.456',
                'channel_name' => 'presence-guild.' . $this->guildId,
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function 存在しないギルドIDはチャンネル認証が拒否される(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->token))
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '123.456',
                'channel_name' => 'presence-guild.99999999',
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function 認証なしではチャンネル認証できない(): void
    {
        $this->guildId = $this->createGuildWithMember($this->sysPlayerId);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'presence-guild.' . $this->guildId,
        ]);

        // auth.token ミドルウェアが弾く
        $response->assertUnauthorized();
    }

    // ============================================================
    // ヘルパ
    // ============================================================

    /** ギルドを作成してIDを返す */
    private function createGuild(): int
    {
        return (int) DB::connection('sys')->table('sys_guild')->insertGetId([
            'name'        => 'テストギルド',
            'description' => null,
            'level'       => 1,
            'exp'         => 0,
            'max_members' => 30,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** ギルドを作成し、指定プレイヤーをメンバーとして追加してギルドIDを返す */
    private function createGuildWithMember(int $sysPlayerId): int
    {
        $guildId = $this->createGuild();

        DB::connection('sys')->table('sys_guild_member')->insert([
            'sys_guild_id'  => $guildId,
            'sys_player_id' => $sysPlayerId,
            'role'          => 'master',
            'joined_at'     => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return $guildId;
    }
}
