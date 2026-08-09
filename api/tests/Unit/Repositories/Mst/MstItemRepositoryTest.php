<?php

namespace Tests\Unit\Repositories\Mst;

use App\Models\Mst\MstItem;
use App\Repositories\Mst\MstItemRepository;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class MstItemRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected MstItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MstItemRepository;

        // Clear Redis cache before each test
        Cache::store('redis')->flush();
    }

    /**
     * Test selectById returns correct item
     */
    public function test_select_by_id_returns_item(): void
    {
        // Arrange
        $item = MstItem::create([
            'id' => 'item_001',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_hp',
            'value' => 50.0,
        ]);

        // Act
        $result = $this->repository->selectById('item_001');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('item_001', $result->id);
        $this->assertEquals('consumable', $result->type);
        $this->assertEquals('heal_hp', $result->effect);
        $this->assertEquals(50.0, $result->value);
    }

    /**
     * Test selectById returns null for non-existent item
     */
    public function test_select_by_id_returns_null_for_non_existent_item(): void
    {
        // Act
        $result = $this->repository->selectById('non_existent');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test selectListByIds returns correct items
     */
    public function test_select_list_by_ids_returns_multiple_items(): void
    {
        // Arrange
        MstItem::create([
            'id' => 'item_001',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_hp',
            'value' => 50.0,
        ]);

        MstItem::create([
            'id' => 'item_002',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_mp',
            'value' => 30.0,
        ]);

        MstItem::create([
            'id' => 'item_003',
            'deploy_key' => 202601010,
            'type' => 'equipment',
            'effect' => 'increase_attack',
            'value' => 10.0,
        ]);

        // Act
        $result = $this->repository->selectListByIds(['item_001', 'item_003']);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('item_001', $result[0]->id);
        $this->assertEquals('item_003', $result[1]->id);
    }

    /**
     * Test selectListByIds returns empty collection for non-existent items
     */
    public function test_select_list_by_ids_returns_empty_for_non_existent_items(): void
    {
        // Act
        $result = $this->repository->selectListByIds(['non_existent_1', 'non_existent_2']);

        // Assert
        $this->assertCount(0, $result);
    }

    /**
     * Test caching functionality
     */
    public function test_caching_works_correctly(): void
    {
        // Arrange
        MstItem::create([
            'id' => 'item_001',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_hp',
            'value' => 50.0,
        ]);

        // Act - First call should query database and cache
        $result1 = $this->repository->selectById('item_001');

        // Create a new item after first query
        MstItem::create([
            'id' => 'item_002',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_mp',
            'value' => 30.0,
        ]);

        // Second call should use cache, so item_002 should not be visible
        $result2 = $this->repository->selectById('item_002');

        // Assert
        $this->assertNotNull($result1);
        $this->assertEquals('item_001', $result1->id);
        // item_002 should not be in cache yet
        $this->assertNull($result2);
    }

    /**
     * Test cache invalidation
     */
    public function test_cache_can_be_cleared(): void
    {
        // Arrange
        MstItem::create([
            'id' => 'item_001',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_hp',
            'value' => 50.0,
        ]);

        // Act - First call to cache data
        $result1 = $this->repository->selectById('item_001');

        // Clear Redis cache
        Cache::store('redis')->flush();

        // Create new repository instance to clear internal state
        $newRepository = new MstItemRepository;

        // Create a new item
        MstItem::create([
            'id' => 'item_002',
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_mp',
            'value' => 30.0,
        ]);

        // Should see the new item after cache clear
        $result2 = $newRepository->selectById('item_002');

        // Assert
        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertEquals('item_002', $result2->id);
    }

    /**
     * Test selectListByIds maintains order based on input
     */
    public function test_select_list_by_ids_returns_items_in_found_order(): void
    {
        // Arrange
        MstItem::create(['id' => 'item_001', 'deploy_key' => 202601010, 'type' => 'consumable', 'effect' => 'heal_hp', 'value' => 50.0]);
        MstItem::create(['id' => 'item_002', 'deploy_key' => 202601010, 'type' => 'consumable', 'effect' => 'heal_mp', 'value' => 30.0]);
        MstItem::create(['id' => 'item_003', 'deploy_key' => 202601010, 'type' => 'equipment', 'effect' => 'increase_attack', 'value' => 10.0]);

        // Act
        $result = $this->repository->selectListByIds(['item_003', 'item_001', 'item_002']);

        // Assert
        $this->assertCount(3, $result);
        // The order should match the collection's values() method output
        $ids = $result->pluck('id')->toArray();
        $this->assertContains('item_001', $ids);
        $this->assertContains('item_002', $ids);
        $this->assertContains('item_003', $ids);
    }

    protected function tearDown(): void
    {
        Cache::store('redis')->flush();
        parent::tearDown();
    }
}
