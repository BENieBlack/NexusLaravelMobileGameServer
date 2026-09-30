<?php

namespace Tests\Feature;

use App\Models\Adm\AdmAccount;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'admin', '--path' => 'database/migrations/adm', '--force' => true]);
        $this->actingAs(AdmAccount::updateOrCreate(
            ['email' => 'player-search@example.com'],
            ['name' => 'operator', 'password' => Hash::make('password')],
        ));
    }

    #[Test]
    public function プレイヤー検索はmy_idとuuidで絞り込み情報画面へ遷移できる(): void
    {
        DB::connection('sys')->table('sys_player')->updateOrInsert(
            ['my_id' => 'PLAYER01'],
            ['uuid' => 'player-uuid', 'name' => 'テストプレイヤー', 'created_at' => now(), 'updated_at' => now()],
        );

        $this->get(route('player.list', ['q' => 'PLAYER01']))
            ->assertOk()
            ->assertSee('テストプレイヤー')
            ->assertSee(route('player.index', ['player_id' => 1]));

        $this->get(route('player.list', ['q' => 'player-uuid']))
            ->assertOk()
            ->assertSee('PLAYER01');
    }

    #[Test]
    public function プレイヤー情報から履歴へ切り替えられる(): void
    {
        DB::connection('sys')->table('sys_player')->updateOrInsert(
            ['my_id' => 'PLAYER02'],
            ['uuid' => 'player-uuid-2', 'name' => '別プレイヤー', 'created_at' => now(), 'updated_at' => now()],
        );

        $player = DB::connection('sys')->table('sys_player')->where('my_id', 'PLAYER02')->first();

        $this->get(route('player.index', ['player_id' => $player->id]))
            ->assertOk()
            ->assertSee('情報')
            ->assertSee(route('player.log', ['player_id' => $player->id]));
    }
}
