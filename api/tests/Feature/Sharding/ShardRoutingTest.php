<?php

namespace Tests\Feature\Sharding;

use App\Models\Trx\TrxUnit;
use App\Persistence\ApiSession;
use App\Repositories\Log\LogVipPointRepository;
use App\Repositories\Trx\TrxUnitRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * プレイヤーのシャードへ読み書きが向くかのテスト
 *
 * trx_* / log_* はプレイヤーごとにシャードへ分かれる。
 * 割り当てはサインアップ時に sys_sharding_node_player へ作られ、
 * モデルとRepositoryはその割り当てから接続先を決める。
 *
 * ここが繋がっていないと、全プレイヤーのデータが trx1 に集まってしまう。
 */
class ShardRoutingTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function tearDown(): void
    {
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function サインアップでシャードが割り当てられる(): void
    {
        ['player' => $player] = $this->signUpPlayer();

        $assignment = DB::connection('sys')->table('sys_sharding_node_player')
            ->where('sys_player_id', $player->id)->first();

        $this->assertNotNull($assignment, 'サインアップで割り当てが作られていない');
    }

    #[Test]
    public function 割り当ては複数ノードに散らばる(): void
    {
        $nodeNos = [];

        for ($i = 0; $i < 6; $i++) {
            ['player' => $player] = $this->signUpPlayer();
            $nodeNos[] = $this->playerConnection($player->id);
        }

        $this->assertGreaterThan(
            1,
            count(array_unique($nodeNos)),
            '全員が同じシャードに寄っている'
        );
    }

    #[Test]
    public function 同じプレイヤーは何度呼んでも同じシャードになる(): void
    {
        ['player' => $player] = $this->signUpPlayer();

        $first = $this->playerConnection($player->id);

        $this->assertSame($first, $this->playerConnection($player->id));
        $this->assertSame(
            1,
            DB::connection('sys')->table('sys_sharding_node_player')
                ->where('sys_player_id', $player->id)->count(),
            '割り当てが重複して作られている'
        );
    }

    #[Test]
    public function repositoryとモデルは割り当てシャードを向く(): void
    {
        ['player' => $player] = $this->signUpPlayer();
        $expected = $this->playerConnection($player->id);

        ApiSession::clearForTest();
        $this->app->forgetScopedInstances();
        ApiSession::setSysPlayerId($player->id);

        $this->assertSame($expected, app(TrxUnitRepository::class)->getConnection());
        $this->assertSame($expected, (new TrxUnit)->getConnectionName());
    }

    #[Test]
    public function ログは対になるlogシャードを向く(): void
    {
        ['player' => $player] = $this->signUpPlayer();
        $expectedLog = $this->playerLogConnection($player->id);

        ApiSession::clearForTest();
        $this->app->forgetScopedInstances();
        ApiSession::setSysPlayerId($player->id);

        $this->assertSame($expectedLog, app(LogVipPointRepository::class)->getConnection());
    }

    #[Test]
    public function プレイヤーが居なければ既定の接続を使う(): void
    {
        // コンソールなどプレイヤーの居ない文脈では trx1 / log1 に落ちる
        ApiSession::clearForTest();
        $this->app->forgetScopedInstances();

        $this->assertSame('trx1', (new TrxUnit)->getConnectionName());
        $this->assertSame('trx1', app(TrxUnitRepository::class)->getConnection());
    }

    #[Test]
    public function 明示的に指定した接続が優先される(): void
    {
        ['player' => $player] = $this->signUpPlayer();

        ApiSession::clearForTest();
        $this->app->forgetScopedInstances();
        ApiSession::setSysPlayerId($player->id);

        // バッチ等が setConnection() / ::on() で指定した場合は自動解決より優先する
        $repository = app(TrxUnitRepository::class);
        $repository->setConnection('trx3');
        $this->assertSame('trx3', $repository->getConnection());

        $unit = new TrxUnit;
        $unit->setConnection('trx3');
        $this->assertSame('trx3', $unit->getConnectionName());
    }

    #[Test]
    public function 書き込みが割り当てシャードへ入る(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $expected = $this->playerConnection($player->id);

        // ログインでログインボーナス履歴が書かれる
        $this->withHeaders($this->authHeaders($token))->postJson('/api/auth/login')->assertOk();

        foreach (['trx1', 'trx2'] as $connection) {
            $count = DB::connection($connection)->table('trx_login_bonus_history')
                ->where('sys_player_id', $player->id)->count();

            if ($connection === $expected) {
                continue;
            }

            $this->assertSame(0, $count, "{$connection} に他シャードのデータが入っている");
        }
    }
}
