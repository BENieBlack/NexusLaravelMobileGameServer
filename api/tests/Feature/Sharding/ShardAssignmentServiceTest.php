<?php

namespace Tests\Feature\Sharding;

use App\Domain\Sharding\Services\ShardAssignmentService;
use App\Models\Sys\SysSharding;
use App\Models\Sys\SysShardingNode;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ShardAssignmentService のテスト
 *
 * プレイヤーのデータがどのDBに載るかを決める。一度決めたら動かせない
 * （動かすと過去のデータが読めなくなる）ので、再割り当てしないことが要点。
 *
 * 接続設定に無いノードへ割り当てると、その行を以降読み書きできなくなる。
 */
class ShardAssignmentServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const SHARDING_NAME = 'trx_sharding';

    private int $sysPlayerId = 7001;

    private ShardAssignmentService $service;

    public function beginDatabaseTransaction(): void
    {
        // 割り当ては素のクエリビルダで書くため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');
        $this->service = app(ShardAssignmentService::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ClockUtility::reset();

        parent::tearDown();
    }

    // ========================================
    // 再割り当てしない
    // ========================================

    #[Test]
    public function 未割り当てなら割り当てる(): void
    {
        $this->makeSharding();
        $this->makeNode(1);

        $this->assertSame(1, $this->service->assign($this->sysPlayerId));
        $this->assertSame(1, $this->service->findAssignedNodeNo($this->sysPlayerId));
    }

    #[Test]
    public function 割り当て済みなら同じノードを返す(): void
    {
        // ここで別のノードを返すと、過去のデータが読めなくなる
        $this->makeSharding();
        $this->makeNode(1);
        $this->makeNode(2);

        $first = $this->service->assign($this->sysPlayerId);
        $second = $this->service->assign($this->sysPlayerId);

        $this->assertSame($first, $second);
        $this->assertSame(1, $this->countAssignments(), '割り当ては増えない');
    }

    #[Test]
    public function 割り当て済みならノード指定でも動かさない(): void
    {
        $this->makeSharding();
        $this->makeNode(1);
        $this->makeNode(2);
        $this->service->assignToNode($this->sysPlayerId, 1);

        $this->assertFalse($this->service->assignToNode($this->sysPlayerId, 2));
        $this->assertSame(1, $this->service->findAssignedNodeNo($this->sysPlayerId));
    }

    #[Test]
    public function 未割り当てならnullを返す(): void
    {
        $this->assertNull($this->service->findAssignedNodeNo($this->sysPlayerId));
    }

    #[Test]
    public function 割り当てるとノードの人数が増える(): void
    {
        $this->makeSharding();
        $this->makeNode(1);

        $this->service->assign($this->sysPlayerId);

        $this->assertSame(1, $this->nodePlayerCount(1));
    }

    // ========================================
    // ノードの選び方
    // ========================================

    #[Test]
    public function hash戦略はプレイヤーidでノードを決める(): void
    {
        $this->makeSharding(SysSharding::STRATEGY_HASH);
        $this->makeNode(1);
        $this->makeNode(2);

        // ノードは node_no 昇順。偶数IDは1つ目、奇数IDは2つ目へ
        $this->assertSame(1, $this->service->assign(1000));
        $this->assertSame(2, $this->service->assign(1001));
    }

    #[Test]
    public function hash以外は人数の少ないノードへ寄せる(): void
    {
        $this->makeSharding(SysSharding::STRATEGY_RANGE);
        $this->makeNode(1, playerCount: 100);
        $this->makeNode(2, playerCount: 3);

        $this->assertSame(2, $this->service->assign($this->sysPlayerId));
    }

    #[Test]
    public function 人数が同じならノード番号の小さい方へ寄せる(): void
    {
        $this->makeSharding(SysSharding::STRATEGY_RANGE);
        $this->makeNode(2, playerCount: 5);
        $this->makeNode(1, playerCount: 5);

        $this->assertSame(1, $this->service->assign($this->sysPlayerId));
    }

    #[Test]
    public function 書き込み不可のノードは選ばない(): void
    {
        $this->makeSharding();
        $this->makeNode(1, isWritable: false);
        $this->makeNode(2);

        $this->assertSame(2, $this->service->assign($this->sysPlayerId));
    }

    #[Test]
    public function 停止中のノードは選ばない(): void
    {
        $this->makeSharding();
        $this->makeNode(1, status: SysShardingNode::STATUS_MAINTENANCE);
        $this->makeNode(2);

        $this->assertSame(2, $this->service->assign($this->sysPlayerId));
    }

    #[Test]
    public function 接続設定に無いノードは選ばない(): void
    {
        // sys_sharding_node の行数と DB_SHARD_COUNT がずれていても、
        // 読み書きできないノードへ割り当ててはいけない
        $this->makeSharding();
        $this->makeNode(1);
        $this->makeNode(9);

        config(['database.pitr.active_trx_connections' => ['trx1']]);

        $this->assertSame(1, $this->service->assign($this->sysPlayerId));
    }

    // ========================================
    // 割り当てられない場合
    // ========================================

    #[Test]
    public function シャーディング定義が無ければ例外(): void
    {
        $this->makeNode(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Active sharding definition not found');

        $this->service->assign($this->sysPlayerId);
    }

    #[Test]
    public function 無効化された定義は使わない(): void
    {
        $this->makeSharding(isActive: false);
        $this->makeNode(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Active sharding definition not found');

        $this->service->assign($this->sysPlayerId);
    }

    #[Test]
    public function 書き込めるノードが無ければ例外(): void
    {
        $this->makeSharding();
        $this->makeNode(1, isWritable: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No writable sharding node available');

        $this->service->assign($this->sysPlayerId);
    }

    #[Test]
    public function 接続設定に無いノードだけなら例外(): void
    {
        $this->makeSharding();
        $this->makeNode(9);

        config(['database.pitr.active_trx_connections' => ['trx1']]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No writable sharding node available');

        $this->service->assign($this->sysPlayerId);
    }

    #[Test]
    public function 存在しないノードは指定できない(): void
    {
        $this->makeSharding();
        $this->makeNode(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Writable sharding node not found: node_no=9');

        $this->service->assignToNode($this->sysPlayerId, 9);
    }

    #[Test]
    public function 書き込み不可のノードは指定できない(): void
    {
        $this->makeSharding();
        $this->makeNode(1, isWritable: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Writable sharding node not found: node_no=1');

        $this->service->assignToNode($this->sysPlayerId, 1);
    }

    private function makeSharding(
        string $strategy = SysSharding::STRATEGY_HASH,
        bool $isActive = true,
    ): void {
        DB::connection('sys')->table('sys_sharding')->insert([
            'name' => self::SHARDING_NAME,
            'target' => 'transaction',
            'strategy' => $strategy,
            'sharding_key' => 'sys_player_id',
            'node_count' => 2,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeNode(
        int $nodeNo,
        bool $isWritable = true,
        string $status = SysShardingNode::STATUS_ACTIVE,
        int $playerCount = 0,
    ): void {
        $shardingId = DB::connection('sys')->table('sys_sharding')
            ->where('name', self::SHARDING_NAME)->value('id');

        DB::connection('sys')->table('sys_sharding_node')->insert([
            'sys_sharding_id' => $shardingId ?? 0,
            'node_name' => "node{$nodeNo}",
            'node_no' => $nodeNo,
            'weight' => 100,
            'status' => $status,
            'is_writable' => $isWritable,
            'is_readable' => true,
            'max_connections' => 100,
            'current_player_count' => $playerCount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function countAssignments(): int
    {
        return DB::connection('sys')->table('sys_sharding_node_player')
            ->where('sys_player_id', $this->sysPlayerId)->count();
    }

    private function nodePlayerCount(int $nodeNo): int
    {
        return (int) DB::connection('sys')->table('sys_sharding_node')
            ->where('node_no', $nodeNo)->value('current_player_count');
    }

    private function cleanUp(): void
    {
        DB::connection('sys')->table('sys_sharding_node_player')->delete();
        DB::connection('sys')->table('sys_sharding_node')->delete();
        DB::connection('sys')->table('sys_sharding')->delete();
    }
}
