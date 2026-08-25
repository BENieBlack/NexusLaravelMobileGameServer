<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxItemRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * リクエストスコープで共有されるRepositoryのキャッシュのテスト
 *
 * Repositoryはリクエスト単位で共有され、取得したモデルをインスタンス内に
 * キャッシュする。そのため「キャッシュが誰のものか」を持たないと、
 * プレイヤーを切り替えたときに他人のデータを返してしまう。
 */
class SharedRepositoryCacheTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $playerA = 101;

    private int $playerB = 102;

    public function beginDatabaseTransaction(): void
    {
        // 複数シャードにまたがるためQueryManagerを介さず直接クリーンアップする
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        $this->assignShards();
        $this->cleanUp();
        $this->createItem($this->playerA, 'item_a', 10);
        $this->createItem($this->playerB, 'item_b', 20);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 同じインスタンスが共有される(): void
    {
        $this->assertSame(
            app(TrxItemRepository::class),
            app(TrxItemRepository::class)
        );
    }

    #[Test]
    public function プレイヤーを切り替えるとキャッシュを取り直す(): void
    {
        $repository = app(TrxItemRepository::class);

        ApiSession::setSysPlayerId($this->playerA);
        $itemsOfA = $repository->queryOrMemory();
        $this->assertSame(['item_a'], $itemsOfA->pluck('mst_item_id')->all());

        // 同じインスタンスのままプレイヤーを切り替える
        $itemsOfB = $repository->selectBySysPlayerId($this->playerB);
        $this->assertSame(['item_b'], array_map(fn ($item) => $item->getMstItemId(), $itemsOfB));

        // 戻したときもAのデータが返る
        $itemsOfA = $repository->selectBySysPlayerId($this->playerA);
        $this->assertSame(['item_a'], array_map(fn ($item) => $item->getMstItemId(), $itemsOfA));
    }

    #[Test]
    public function 所持なしのプレイヤーは繰り返し問い合わせない(): void
    {
        $repository = app(TrxItemRepository::class);
        ApiSession::setSysPlayerId($this->playerA);

        DB::connection(ApiSession::resolveConnectionName('trx'))
            ->table('trx_item')->where('sys_player_id', $this->playerA)->delete();

        // 1回目でDBを引き、0件をキャッシュする
        $this->assertCount(0, $repository->queryOrMemory());

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (str_contains($query->sql, 'trx_item')) {
                $queryCount++;
            }
        });

        $repository->queryOrMemory();
        $repository->queryOrMemory();

        $this->assertSame(0, $queryCount, '0件でもキャッシュから返るべき');
    }

    /**
     * プレイヤーをシャードに割り当てる（未割り当てだと接続名を解決できない）
     */
    private function assignShards(): void
    {
        $nodeId = DB::connection('sys')->table('sys_sharding_node')->value('id');

        if ($nodeId === null) {
            $this->artisan('db:seed', [
                '--database' => 'sys',
                '--class' => 'Database\\Seeders\\SysShardingSeeder',
                '--force' => true,
            ]);
            $nodeId = DB::connection('sys')->table('sys_sharding_node')->value('id');
        }

        foreach ([$this->playerA, $this->playerB] as $sysPlayerId) {
            DB::connection('sys')->table('sys_sharding_node_player')->updateOrInsert(
                ['sys_player_id' => $sysPlayerId],
                [
                    'sys_sharding_node_id' => $nodeId,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function createItem(int $sysPlayerId, string $mstItemId, int $amount): void
    {
        ApiSession::setSysPlayerId($sysPlayerId);

        DB::connection(ApiSession::resolveConnectionName('trx'))->table('trx_item')->insert([
            'sys_player_id' => $sysPlayerId,
            'mst_item_id' => $mstItemId,
            'free_amount' => $amount,
            'paid_amount' => 0,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApiSession::clearForTest();
    }

    private function cleanUp(): void
    {
        foreach ([$this->playerA, $this->playerB] as $sysPlayerId) {
            ApiSession::setSysPlayerId($sysPlayerId);
            DB::connection(ApiSession::resolveConnectionName('trx'))
                ->table('trx_item')->where('sys_player_id', $sysPlayerId)->delete();
            ApiSession::clearForTest();
        }

        DB::connection('sys')->table('sys_sharding_node_player')
            ->whereIn('sys_player_id', [$this->playerA, $this->playerB])->delete();
    }
}
