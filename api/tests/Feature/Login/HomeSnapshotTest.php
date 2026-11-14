<?php

namespace Tests\Feature\Login;

use App\Models\Trx\TrxDiamond;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Contracts\ReturnedAsSingleUpdatedResource;
use Nexus\Core\UnitOfWork\Contracts\ReturnedAsUpdatedResource;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * auth/login（Home）は、現在値を全部渡す API なので、updated_resources の対象テーブルを全量で返す
 */
class HomeSnapshotTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function ログインのレスポンスは対象の全テーブルを返す(): void
    {
        ['token' => $token] = $this->signUpPlayer();

        $response = $this->withHeaders($this->authHeaders($token))->postJson('/api/auth/login');

        $response->assertOk();

        // ReturnedAsUpdatedResource を実装したモデルの全部が、キーとして入っている
        // （1行だけのテーブルは {テーブル名}、それ以外は {テーブル名}_list）
        foreach ($this->returnedModels() as $class) {
            $table = (new $class)->getTable();
            $key = is_subclass_of($class, ReturnedAsSingleUpdatedResource::class) ? $table : $table.'_list';

            // login のダイヤだけは、X-Platform の platform の 1 行を trx_diamond（オブジェクト）で返す
            // （D5。updated_resources は従来どおり trx_diamond_list）
            if ($class === TrxDiamond::class) {
                $key = 'trx_diamond';
            }

            $this->assertTrue(
                array_key_exists($key, $response->json()),
                "auth/login のレスポンスに {$key} がありません（{$class}）。現在値を渡す API は、対象の全テーブルを返します"
            );
        }
    }

    #[Test]
    public function スタミナは自然回復を加算した現在値で返し、_d_bには保存しない(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $connection = $this->playerConnection($player->id);

        // 25分前に最後に回復した、スタミナ1（300秒で1ポイント回復するので、5ポイント分の自然回復）
        ClockUtility::setNow('2026-01-15 10:25:00');
        DB::connection($connection)->table('trx_stamina')->updateOrInsert(
            ['sys_player_id' => $player->id, 'type' => 'normal'],
            [
                'current_stamina' => 1,
                'recovery_rate_multiplier' => 1.00,
                'last_recovery_at' => '2026-01-15 10:00:00',
                'is_delete' => false,
            ]
        );

        $response = $this->withHeaders($this->authHeaders($token))->postJson('/api/auth/login');
        $response->assertOk();

        $normal = collect($response->json('trx_stamina_list'))->firstWhere('type', 'normal');
        $this->assertNotNull($normal);
        $this->assertGreaterThan(1, $normal['current_stamina']);
        $this->assertGreaterThan('2026-01-15 10:00:00', $normal['last_recovery_at']);

        // DBは、保存したときの値のまま（自然回復の分は保存しない）
        $stored = DB::connection($connection)->table('trx_stamina')
            ->where('sys_player_id', $player->id)->where('type', 'normal')->first();
        $this->assertSame(1, (int) $stored->current_stamina);
        $this->assertSame('2026-01-15 10:00:00', (string) $stored->last_recovery_at);
    }

    /**
     * @return list<class-string>
     */
    private function returnedModels(): array
    {
        $classes = [];

        foreach (['Sys', 'Trx'] as $group) {
            foreach (glob(dirname(__DIR__, 3)."/app/Models/{$group}/*.php") ?: [] as $file) {
                $class = "App\\Models\\{$group}\\".basename($file, '.php');

                if (class_exists($class) && is_subclass_of($class, ReturnedAsUpdatedResource::class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }
}
