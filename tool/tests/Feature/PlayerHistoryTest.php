<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Adm\AdmAccount;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'admin', '--path' => 'database/migrations/adm', '--force' => true]);
        DB::connection('sys')->table('sys_player')->updateOrInsert(
            ['my_id' => 'PLAYER01'],
            ['uuid' => 'player-uuid', 'name' => 'テストプレイヤー'],
        );
        DB::connection('log')->statement('CREATE TABLE IF NOT EXISTS log_change_trx_player (id BIGINT PRIMARY KEY, sys_player_id BIGINT, operation_type VARCHAR(20), created_at DATETIME NULL)');
        $this->actingAs(AdmAccount::updateOrCreate(
            ['email' => 'history@example.com'],
            ['name' => 'history', 'password' => Hash::make('password')],
        ));
    }

    #[Test]
    public function プレイヤー履歴画面でログタブを切り替えられる(): void
    {
        $this->get(route('player.log', ['player_id' => 1]))
            ->assertOk()
            ->assertSee('プレイヤー履歴')
            ->assertSee('log_change_trx_player');

        $this->get(route('player.log', ['player_id' => 1, 'tab' => 'change_player']))
            ->assertOk()
            ->assertSee('log_change_trx_player');
    }
}
