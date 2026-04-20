<?php

namespace Tests\Unit\Repositories\Trx;

use App\Models\Trx\TrxItem;
use App\Repositories\Trx\TrxItemRepository;
use App\Persistence\QueryManager;
use App\Persistence\ApiSession;
use App\Utilities\Clock;
use Illuminate\Support\Facades\DB;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class TrxItemRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected TrxItemRepository $repository;
    protected QueryManager $queryManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ApiSessionを初期化（テスト用のプレイヤーID=1を設定）
        Clock::initialize();
        ApiSession::setSysPlayerId(1);
        
        $this->repository = new TrxItemRepository();
        $this->queryManager = new QueryManager();
    }

    /**
     * free_amountとpaid_amountの相対的な減算が正しく動作することをテスト
     */
    public function test_amount_relative_decrease_works_correctly(): void
    {
        // Arrange: アイテムを作成（free_amount=80, paid_amount=20）
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_001',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // Act: paid_amountを10減らす（既存の方法）
        $item->setPaidAmount($item->paid_amount - 10);
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから再取得して確認
        $updatedItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_001')
            ->first();
        
        $this->assertEquals(80, $updatedItem->free_amount);
        $this->assertEquals(10, $updatedItem->paid_amount);
    }

    /**
     * free_amountとpaid_amountの相対的な増加が正しく動作することをテスト
     */
    public function test_amount_relative_increase_works_correctly(): void
    {
        // Arrange: アイテムを作成（free_amount=40, paid_amount=10）
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_002',
            'free_amount' => 40,
            'paid_amount' => 10,
        ]);

        // Act: free_amountを20増やす（既存の方法）
        $item->setFreeAmount($item->free_amount + 20);
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから再取得して確認
        $updatedItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_002')
            ->first();
        
        $this->assertEquals(60, $updatedItem->free_amount);
        $this->assertEquals(10, $updatedItem->paid_amount);
    }

    /**
     * 複数の相対的な変更が累積されることをテスト
     */
    public function test_multiple_relative_changes_accumulate(): void
    {
        // Arrange: アイテムを作成（free_amount=80, paid_amount=20）
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_003',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // Act: 複数回の相対的な変更
        $item->setPaidAmount($item->paid_amount - 10); // 20 - 10 = 10
        $item->setPaidAmount($item->paid_amount - 5);  // 10 - 5 = 5
        $item->setFreeAmount($item->free_amount + 15); // 80 + 15 = 95
        
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから再取得して確認
        $updatedItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_003')
            ->first();
        
        $this->assertEquals(95, $updatedItem->free_amount);
        $this->assertEquals(5, $updatedItem->paid_amount);
    }

    /**
     * 競合状態シミュレーション: 2つのリクエストが同時にアイテムを消費
     * 相対的な更新により、両方の減算が正しく反映されることを確認
     */
    public function test_concurrent_requests_with_relative_updates(): void
    {
        // Arrange: アイテムを作成（free_amount=80, paid_amount=20）
        $initialItem = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_004',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // リクエスト1: DBから読み込み（free_amount=80, paid_amount=20）
        $item1 = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_004')
            ->first();
        
        // リクエスト2: DBから読み込み（free_amount=80, paid_amount=20）
        $item2 = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_004')
            ->first();

        // リクエスト1: paid_amount - 10（相対的な更新）
        $item1->setPaidAmount($item1->paid_amount - 10);
        $repository1 = new TrxItemRepository();
        $repository1->setModel($item1);
        $queryManager1 = new QueryManager();
        $queryManager1->registerRepository($repository1);
        $queryManager1->execAllQuery();

        // リクエスト2: free_amount - 20（相対的な更新）
        // 注意: item2はまだfree_amount=80を持っているが、相対的な更新により-20が正しく適用される
        $item2->setFreeAmount($item2->free_amount - 20);
        
        $repository2 = new TrxItemRepository();
        $repository2->setModel($item2);
        $queryManager2 = new QueryManager();
        $queryManager2->registerRepository($repository2);
        $queryManager2->execAllQuery();

        // Assert: DBから再取得して確認
        // 相対的な更新により、free_amount=80-20=60, paid_amount=20-10=10 になるべき
        $finalItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_004')
            ->first();
        
        $this->assertEquals(60, $finalItem->free_amount);
        $this->assertEquals(10, $finalItem->paid_amount);
    }

    /**
     * 新規作成時は相対的な更新が適用されないことを確認
     */
    public function test_new_item_does_not_use_relative_updates(): void
    {
        // Arrange & Act: 新規アイテムを作成
        $item = new TrxItem([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_005',
            'free_amount' => 40,
            'paid_amount' => 10,
        ]);
        $item->exists = false;

        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから確認
        $createdItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_005')
            ->first();
        
        $this->assertNotNull($createdItem);
        $this->assertEquals(40, $createdItem->free_amount);
        $this->assertEquals(10, $createdItem->paid_amount);
    }

    /**
     * 相対的な変更がクリアされることを確認
     */
    public function test_relative_changes_are_cleared_after_update(): void
    {
        // Arrange: アイテムを作成
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_006',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // Act: 相対的な変更を記録
        $item->setPaidAmount($item->paid_amount - 10);
        
        // 相対的な変更があることを確認
        $this->assertTrue($item->hasRelativeChanges());
        
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: 相対的な変更がクリアされたことを確認
        $this->assertFalse($item->hasRelativeChanges());
    }
}
