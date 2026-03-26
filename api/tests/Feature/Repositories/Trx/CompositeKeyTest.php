<?php

namespace Tests\Feature\Repositories\Trx;

use App\Repositories\Trx\TrxItemRepository;
use App\Utilities\ApiSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 複合キーのテスト（TrxItemRepository）
 */
class CompositeKeyTest extends TestCase
{
    use RefreshDatabase;

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

    /** @test */
    public function 複合キーが正しく設定されている(): void
    {
        $this->insertTestData();
        
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxItemRepository();
        
        $reflection = new \ReflectionClass($repo);
        $uniqueKeysProperty = $reflection->getProperty('uniqueKeys');
        $uniqueKeysProperty->setAccessible(true);
        $uniqueKeys = $uniqueKeysProperty->getValue($repo);
        
        $this->assertSame(['sys_player_id', 'mst_item_id'], $uniqueKeys);
    }

    /** @test */
    public function queryOrMemoryでデータ取得ができる(): void
    {
        $this->insertTestData();
        
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxItemRepository();
        
        $items = $repo->queryOrMemory();
        
        $this->assertSame(2, $items->count());
        
        $item1 = $items->where('mst_item_id', 'item_001')->first();
        $this->assertNotNull($item1);
        $this->assertSame(10, $item1->amount);
        
        $item2 = $items->where('mst_item_id', 'item_002')->first();
        $this->assertNotNull($item2);
        $this->assertSame(5, $item2->amount);
    }

    /** @test */
    public function 複合キーでのキャッシュ動作が正しい(): void
    {
        $this->insertTestData();
        
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxItemRepository();
        
        $items = $repo->queryOrMemory();
        $item = $items->first();
        
        $this->assertNotNull($item);
        
        $originalAmount = $item->amount;
        $item->amount = $originalAmount + 10;
        
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
        $this->assertSame($item->amount, $cached->amount);
    }

    private function insertTestData(): void
    {
        DB::connection('trx1')->table('trx_item')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'item_001',
                'amount' => 10,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'item_002',
                'amount' => 5,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
