<?php

namespace Tests\Unit\Repositories\Trx;

use App\Models\Trx\TrxItem;
use App\Repositories\Trx\TrxItemRepository;
use App\Repositories\QueryManager;
use App\Utilities\ApiSession;
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
     * amountの相対的な減算が正しく動作することをテスト
     */
    public function test_amount_relative_decrease_works_correctly(): void
    {
        // Arrange: アイテムを作成（amount=100）
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_001',
            'amount' => 100,
        ]);

        // Act: amountを10減らす（既存の方法）
        $item->setAmount($item->amount - 10);
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから再取得して確認
        $updatedItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_001')
            ->first();
        
        $this->assertEquals(90, $updatedItem->amount);
    }

    /**
     * amountの相対的な増加が正しく動作することをテスト
     */
    public function test_amount_relative_increase_works_correctly(): void
    {
        // Arrange: アイテムを作成（amount=50）
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_002',
            'amount' => 50,
        ]);

        // Act: amountを20増やす（既存の方法）
        $item->setAmount($item->amount + 20);
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから再取得して確認
        $updatedItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_002')
            ->first();
        
        $this->assertEquals(70, $updatedItem->amount);
    }

    /**
     * 複数の相対的な変更が累積されることをテスト
     */
    public function test_multiple_relative_changes_accumulate(): void
    {
        // Arrange: アイテムを作成（amount=100）
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_003',
            'amount' => 100,
        ]);

        // Act: 複数回の相対的な変更
        $item->setAmount($item->amount - 10); // 100 - 10 = 90
        $item->setAmount($item->amount - 5);  // 90 - 5 = 85
        $item->setAmount($item->amount + 15); // 85 + 15 = 100
        
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: DBから再取得して確認
        $updatedItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_003')
            ->first();
        
        $this->assertEquals(100, $updatedItem->amount);
    }

    /**
     * 競合状態シミュレーション: 2つのリクエストが同時にアイテムを消費
     * 相対的な更新により、両方の減算が正しく反映されることを確認
     */
    public function test_concurrent_requests_with_relative_updates(): void
    {
        // Arrange: アイテムを作成（amount=100）
        $initialItem = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'item_004',
            'amount' => 100,
        ]);

        // リクエスト1: DBから読み込み（amount=100）
        $item1 = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_004')
            ->first();
        
        // リクエスト2: DBから読み込み（amount=100）
        $item2 = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_004')
            ->first();

        // リクエスト1: amount - 10（相対的な更新）
        $item1->setAmount($item1->amount - 10);
        $repository1 = new TrxItemRepository();
        $repository1->setModel($item1);
        $queryManager1 = new QueryManager();
        $queryManager1->registerRepository($repository1);
        $queryManager1->execAllQuery();

        // リクエスト2: amount - 20（相対的な更新）
        // 注意: item2はまだamount=100を持っているが、相対的な更新により-20が正しく適用される
        $item2->setAmount($item2->amount - 20);
        
        $repository2 = new TrxItemRepository();
        $repository2->setModel($item2);
        $queryManager2 = new QueryManager();
        $queryManager2->registerRepository($repository2);
        $queryManager2->execAllQuery();

        // Assert: DBから再取得して確認
        // 相対的な更新により、100 - 10 - 20 = 70 になるべき
        $finalItem = TrxItem::where('sys_player_id', 1)
            ->where('mst_item_id', 'item_004')
            ->first();
        
        $this->assertEquals(70, $finalItem->amount);
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
            'amount' => 50,
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
        $this->assertEquals(50, $createdItem->amount);
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
            'amount' => 100,
        ]);

        // Act: 相対的な変更を記録
        $item->setAmount($item->amount - 10);
        
        // 相対的な変更があることを確認
        $this->assertTrue($item->hasRelativeChanges());
        
        $this->repository->setModel($item);
        $this->queryManager->registerRepository($this->repository);
        $this->queryManager->execAllQuery();

        // Assert: 相対的な変更がクリアされたことを確認
        $this->assertFalse($item->hasRelativeChanges());
    }
}
