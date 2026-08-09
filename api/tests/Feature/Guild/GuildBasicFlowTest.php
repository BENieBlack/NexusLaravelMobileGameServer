<?php

namespace Tests\Feature\Guild;

use App\Models\Sys\SysGuild;
use App\Models\Sys\SysGuildMember;
use App\Models\Sys\SysPlayer;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * GuildBasicFlowTest
 *
 * ギルド機能の基本フローテスト
 */
class GuildBasicFlowTest extends TestCase
{
    use RefreshMultipleDatabases;

    /**
     * ギルド作成のテスト
     */
    public function test_guild_create(): void
    {
        // テストプレイヤーを作成
        $player = SysPlayer::create([
            'uuid' => 'test-uuid-'.uniqid(),
            'my_id' => 'TEST'.rand(1000, 9999),
            'name' => 'Test Player',
            'level' => 1,
            'level_exp' => 0,
        ]);

        // ギルド作成
        $response = $this->postJson('/api/guild/create', [
            'authenticated_player_id' => $player->id,
            'name' => 'Test Guild',
            'description' => 'This is a test guild',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'guild_id',
                'name',
                'description',
                'level',
                'exp',
                'max_members',
                'current_members',
                'created_at',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals('Test Guild', $data['name']);
        $this->assertEquals('This is a test guild', $data['description']);
        $this->assertEquals(1, $data['level']);
        $this->assertEquals(0, $data['exp']);
        $this->assertEquals(1, $data['current_members']); // マスターが自動追加

        // DBに保存されていることを確認（テスト環境ではDB名が異なる）
        $this->assertDatabaseHas('sys_guild', [
            'name' => 'Test Guild',
        ], 'sys');

        // メンバーテーブルにマスターが追加されていることを確認
        $this->assertDatabaseHas('sys_guild_member', [
            'sys_player_id' => $player->id,
            'role' => 'master',
        ], 'sys');
    }

    /**
     * ギルド一覧取得のテスト
     */
    public function test_guild_list(): void
    {
        // テストギルドを3つ作成
        $player1 = SysPlayer::create([
            'uuid' => 'test-uuid-1-'.uniqid(),
            'my_id' => 'TST1'.rand(1000, 9999),
            'name' => 'Player 1',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $player2 = SysPlayer::create([
            'uuid' => 'test-uuid-2-'.uniqid(),
            'my_id' => 'TST2'.rand(1000, 9999),
            'name' => 'Player 2',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $player3 = SysPlayer::create([
            'uuid' => 'test-uuid-3-'.uniqid(),
            'my_id' => 'TST3'.rand(1000, 9999),
            'name' => 'Player 3',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $guild1 = SysGuild::create([
            'name' => 'Guild A',
            'description' => 'First guild',
            'level' => 1,
            'exp' => 0,
            'max_members' => 30,
        ]);
        SysGuildMember::create([
            'sys_guild_id' => $guild1->id,
            'sys_player_id' => $player1->id,
            'role' => 'master',
            'joined_at' => now(),
        ]);

        $guild2 = SysGuild::create([
            'name' => 'Guild B',
            'description' => 'Second guild',
            'level' => 5,
            'exp' => 1000,
            'max_members' => 30,
        ]);
        SysGuildMember::create([
            'sys_guild_id' => $guild2->id,
            'sys_player_id' => $player2->id,
            'role' => 'master',
            'joined_at' => now(),
        ]);

        $guild3 = SysGuild::create([
            'name' => 'Guild C',
            'description' => 'Third guild',
            'level' => 10,
            'exp' => 5000,
            'max_members' => 30,
        ]);
        SysGuildMember::create([
            'sys_guild_id' => $guild3->id,
            'sys_player_id' => $player3->id,
            'role' => 'master',
            'joined_at' => now(),
        ]);

        // ギルド一覧を取得
        $response = $this->getJson('/api/guild/list');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'guilds' => [
                    '*' => [
                        'guild_id',
                        'name',
                        'description',
                        'level',
                        'exp',
                        'max_members',
                        'current_members',
                        'created_at',
                    ],
                ],
            ],
        ]);

        $guilds = $response->json('data.guilds');
        $this->assertCount(3, $guilds);
    }

    /**
     * ギルド詳細取得のテスト
     */
    public function test_guild_detail(): void
    {
        // テストギルドを作成
        $player = SysPlayer::create([
            'uuid' => 'test-uuid-detail-'.uniqid(),
            'my_id' => 'TSTD'.rand(1000, 9999),
            'name' => 'Detail Player',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $guild = SysGuild::create([
            'name' => 'Detail Test Guild',
            'description' => 'Guild for detail test',
            'level' => 3,
            'exp' => 500,
            'max_members' => 30,
        ]);
        SysGuildMember::create([
            'sys_guild_id' => $guild->id,
            'sys_player_id' => $player->id,
            'role' => 'master',
            'joined_at' => now(),
        ]);

        // ギルド詳細を取得
        $response = $this->getJson('/api/guild/detail?guild_id='.$guild->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'guild_id',
                'name',
                'description',
                'level',
                'exp',
                'max_members',
                'current_members',
                'created_at',
                'updated_at',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals('Detail Test Guild', $data['name']);
        $this->assertEquals(3, $data['level']);
        $this->assertEquals(500, $data['exp']);
        $this->assertEquals(1, $data['current_members']);
    }

    /**
     * ギルドメンバー一覧取得のテスト
     */
    public function test_guild_member_list(): void
    {
        // テストギルドを作成
        $master = SysPlayer::create([
            'uuid' => 'test-uuid-master-'.uniqid(),
            'my_id' => 'MSTR'.rand(1000, 9999),
            'name' => 'Master Player',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $subMaster = SysPlayer::create([
            'uuid' => 'test-uuid-sub-'.uniqid(),
            'my_id' => 'SUBS'.rand(1000, 9999),
            'name' => 'Sub Master Player',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $member = SysPlayer::create([
            'uuid' => 'test-uuid-member-'.uniqid(),
            'my_id' => 'MEMB'.rand(1000, 9999),
            'name' => 'Member Player',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $guild = SysGuild::create([
            'name' => 'Member Test Guild',
            'description' => 'Guild for member test',
            'level' => 1,
            'exp' => 0,
            'max_members' => 30,
        ]);

        SysGuildMember::create([
            'sys_guild_id' => $guild->id,
            'sys_player_id' => $master->id,
            'role' => 'master',
            'joined_at' => now(),
        ]);

        SysGuildMember::create([
            'sys_guild_id' => $guild->id,
            'sys_player_id' => $subMaster->id,
            'role' => 'sub_master',
            'joined_at' => now(),
        ]);

        SysGuildMember::create([
            'sys_guild_id' => $guild->id,
            'sys_player_id' => $member->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // メンバー一覧を取得
        $response = $this->getJson('/api/guild/member/list?guild_id='.$guild->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'members' => [
                    '*' => [
                        'member_id',
                        'guild_id',
                        'player_id',
                        'role',
                        'joined_at',
                    ],
                ],
            ],
        ]);

        $members = $response->json('data.members');
        $this->assertCount(3, $members);

        // 役職を確認
        $roles = array_column($members, 'role');
        $this->assertContains('master', $roles);
        $this->assertContains('sub_master', $roles);
        $this->assertContains('member', $roles);
    }
}
