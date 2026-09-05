<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxGachaRepository;
use App\Repositories\Trx\TrxItemRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 複合キーのテスト（TrxItemRepository）
 */
class CompositeKeyTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        ApiSession::clearForTest();
    }

    protected function tearDown(): void
    {
        ApiSession::clearForTest();
        parent::tearDown();
    }

    #[Test]
    public function 複合キーはmodelの主キーから決まる(): void
    {
        // trx_item の主キーは (sys_player_id, mst_item_id)。
        // Repositoryにもモデルにも書かず、$primaryKey から解決される
        $this->insertTestData();

        $this->useSessionPlayer($this->sysPlayerId);
        $repo = new TrxItemRepository;

        $method = new \ReflectionMethod($repo, 'getUniqueKeys');
        $method->setAccessible(true);

        $this->assertSame(['sys_player_id', 'mst_item_id'], $method->invoke($repo));
    }

    #[Test]
    public function 主キーと論理的な一意が違うテーブルはモデルで明示する(): void
    {
        // trx_gacha の主キーは採番id。二重INSERTを防ぐには
        // uk_player_gacha の (sys_player_id, mst_gacha_id) でキーを組む必要がある
        $this->useSessionPlayer($this->sysPlayerId);
        $repo = new TrxGachaRepository;

        $method = new \ReflectionMethod($repo, 'getUniqueKeys');
        $method->setAccessible(true);

        $this->assertSame(['sys_player_id', 'mst_gacha_id'], $method->invoke($repo));
    }

    #[Test]
    public function query_or_memoryでデータ取得ができる(): void
    {
        $this->insertTestData();

        $this->useSessionPlayer($this->sysPlayerId);
        $repo = new TrxItemRepository;

        $items = $repo->queryOrMemory();

        $this->assertSame(2, $items->count());

        $item1 = $items->where('mst_item_id', 'item_001')->first();
        $this->assertNotNull($item1);
        $this->assertSame(8, $item1->free_amount);
        $this->assertSame(2, $item1->paid_amount);

        $item2 = $items->where('mst_item_id', 'item_002')->first();
        $this->assertNotNull($item2);
        $this->assertSame(4, $item2->free_amount);
        $this->assertSame(1, $item2->paid_amount);
    }

    #[Test]
    public function 複合キーでのキャッシュ動作が正しい(): void
    {
        $this->insertTestData();

        $this->useSessionPlayer($this->sysPlayerId);
        $repo = new TrxItemRepository;

        $items = $repo->queryOrMemory();
        $item = $items->first();

        $this->assertNotNull($item);

        $originalFreeAmount = $item->free_amount;
        $item->free_amount = $originalFreeAmount + 10;

        $reflection = new \ReflectionClass($repo);
        $setModelMethod = $reflection->getMethod('setModel');
        $setModelMethod->setAccessible(true);
        $setModelMethod->invoke($repo, $item);

        // キャッシュから取得
        $cached = $repo->queryOrMemory()
            ->where('sys_player_id', $item->sys_player_id)
            ->where('mst_item_id', $item->mst_item_id)
            ->first();

        $this->assertNotNull($cached);
        $this->assertSame($item->free_amount, $cached->free_amount);
    }

    private function insertTestData(): void
    {
        // 既存データをクリア
        DB::connection('trx1')->table('trx_item')->where('sys_player_id', $this->sysPlayerId)->delete();

        DB::connection('trx1')->table('trx_item')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'item_001',
                'free_amount' => 8,
                'paid_amount' => 2,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'item_002',
                'free_amount' => 4,
                'paid_amount' => 1,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
