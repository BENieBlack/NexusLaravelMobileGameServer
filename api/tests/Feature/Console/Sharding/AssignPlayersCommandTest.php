<?php

namespace Tests\Feature\Console\Sharding;

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * sharding:assign-players のテスト
 *
 * 割り当てはサインアップ時に作られるため、その仕組みが入る前に
 * 作られたプレイヤーには割り当てが無い。
 *
 * 既存データがあるシャードへ寄せることが要点。
 * ハッシュで振り直すと今あるデータへ届かなくなる。
 */
class AssignPlayersCommandTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** @var list<int> */
    private array $sysPlayerIds = [];

    public function beginDatabaseTransaction(): void
    {
        // コマンドが自分でコミットするため自動ラップしない
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 割り当ての無いプレイヤーに割り当てを作る(): void
    {
        $sysPlayerId = $this->makePlayerWithoutAssignment();

        $this->artisan('sharding:assign-players')
            ->expectsOutputToContain('割り当て: 1 人')
            ->assertExitCode(0);

        $this->assertNotNull($this->findAssignedNodeNo($sysPlayerId));
    }

    #[Test]
    public function 既にデータがあるシャードへ寄せる(): void
    {
        // 割り当てが無かった頃のデータは trx2 にある想定
        $sysPlayerId = $this->makePlayerWithoutAssignment();
        $this->makeUnitOn('trx2', $sysPlayerId);

        $this->artisan('sharding:assign-players')
            ->expectsOutputToContain('うち既存データのあるシャードへ寄せた: 1 人')
            ->assertExitCode(0);

        $this->assertSame(
            2,
            $this->findAssignedNodeNo($sysPlayerId),
            'データのあるtrx2へ割り当てられていない'
        );
    }

    #[Test]
    public function データが無ければ通常の選び方に任せる(): void
    {
        $sysPlayerId = $this->makePlayerWithoutAssignment();

        $this->artisan('sharding:assign-players')->assertExitCode(0);

        $this->assertContains($this->findAssignedNodeNo($sysPlayerId), [1, 2]);
    }

    #[Test]
    public function dry_runでは割り当てない(): void
    {
        $sysPlayerId = $this->makePlayerWithoutAssignment();

        $this->artisan('sharding:assign-players', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN モード]')
            ->assertExitCode(0);

        $this->assertNull($this->findAssignedNodeNo($sysPlayerId));
    }

    #[Test]
    public function 割り当て済みのプレイヤーは触らない(): void
    {
        // サインアップ済みのプレイヤーは割り当てを持っている
        ['player' => $player] = $this->signUpPlayer();
        $before = $this->findAssignedNodeNo($player->id);

        $this->artisan('sharding:assign-players')->assertExitCode(0);

        $this->assertSame($before, $this->findAssignedNodeNo($player->id), '割り当ては動かさない');
        $this->assertSame(
            1,
            DB::connection('sys')->table('sys_sharding_node_player')
                ->where('sys_player_id', $player->id)->count(),
            '割り当てが重複して作られている'
        );
    }

    #[Test]
    public function プレイヤーを指定するとその人だけ処理する(): void
    {
        $target = $this->makePlayerWithoutAssignment();
        $other = $this->makePlayerWithoutAssignment();

        $this->artisan('sharding:assign-players', ['--player-id' => $target])
            ->expectsOutputToContain('割り当て: 1 人')
            ->assertExitCode(0);

        $this->assertNotNull($this->findAssignedNodeNo($target));
        $this->assertNull($this->findAssignedNodeNo($other));
    }

    #[Test]
    public function 対象が無ければ何もせず終わる(): void
    {
        $this->artisan('sharding:assign-players')
            ->expectsOutputToContain('割り当てが必要なプレイヤーはいませんでした')
            ->assertExitCode(0);
    }

    /**
     * 割り当てを持たないプレイヤーを作る
     */
    private function makePlayerWithoutAssignment(): int
    {
        $sysPlayerId = DB::connection('sys')->table('sys_player')->insertGetId([
            'uuid' => 'backfill-'.uniqid(),
            'my_id' => substr(uniqid(), -8),
            'name' => 'backfill',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $this->sysPlayerIds[] = $sysPlayerId;

        return $sysPlayerId;
    }

    private function makeUnitOn(string $connection, int $sysPlayerId): void
    {
        DB::connection($connection)->table('trx_unit')->insert([
            'sys_player_id' => $sysPlayerId,
            'mst_unit_id' => 'unit_backfill_001',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);
    }

    private function findAssignedNodeNo(int $sysPlayerId): ?int
    {
        $nodeNo = DB::connection('sys')->table('sys_sharding_node_player as p')
            ->join('sys_sharding_node as n', 'n.id', '=', 'p.sys_sharding_node_id')
            ->where('p.sys_player_id', $sysPlayerId)
            ->value('n.node_no');

        return $nodeNo === null ? null : (int) $nodeNo;
    }

    private function cleanUp(): void
    {
        if ($this->sysPlayerIds === []) {
            return;
        }

        DB::connection('sys')->table('sys_sharding_node_player')
            ->whereIn('sys_player_id', $this->sysPlayerIds)->delete();
        DB::connection('sys')->table('sys_player')
            ->whereIn('id', $this->sysPlayerIds)->delete();

        foreach (['trx1', 'trx2'] as $connection) {
            DB::connection($connection)->table('trx_unit')
                ->whereIn('sys_player_id', $this->sysPlayerIds)->delete();
        }

        $this->sysPlayerIds = [];
    }
}
