<?php

namespace Tests\Unit\Repositories\Mst;

use App\Models\Mst\MstUnit;
use App\Repositories\Mst\MstUnitRepository;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class MstUnitRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected MstUnitRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MstUnitRepository;

        // Clear Redis cache before each test
        Cache::store('redis')->flush();
    }

    /**
     * Test selectById returns correct unit
     */
    public function test_select_by_id_returns_unit(): void
    {
        // Arrange
        $unit = MstUnit::create([
            'id' => 'unit_001',
            'deploy_key' => 202601010,
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SSR',
        ]);

        // Act
        $result = $this->repository->selectById('unit_001');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('unit_001', $result->id);
        $this->assertEquals('Attack', $result->type);
    }

    /**
     * Test selectById returns null for non-existent unit
     */
    public function test_select_by_id_returns_null_for_non_existent_unit(): void
    {
        // Act
        $result = $this->repository->selectById('non_existent');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test selectListByIds returns correct units
     */
    public function test_select_list_by_ids_returns_multiple_units(): void
    {
        // Arrange
        MstUnit::create([
            'id' => 'unit_001',
            'deploy_key' => 202601010,
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SSR',
        ]);

        MstUnit::create([
            'id' => 'unit_002',
            'deploy_key' => 202601010,
            'type' => 'Defense',
            'element' => 'Water',
            'rarity' => 'SR',
        ]);

        MstUnit::create([
            'id' => 'unit_003',
            'deploy_key' => 202601010,
            'type' => 'Support',
            'element' => 'Wind',
            'rarity' => 'R',
        ]);

        // Act
        $result = $this->repository->selectListByIds(['unit_001', 'unit_003']);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('unit_001', $result[0]->id);
        $this->assertEquals('unit_003', $result[1]->id);
    }

    /**
     * Test selectListByIds returns empty collection for non-existent units
     */
    public function test_select_list_by_ids_returns_empty_for_non_existent_units(): void
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
        MstUnit::create([
            'id' => 'unit_001',
            'deploy_key' => 202601010,
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SSR',
        ]);

        // Act - First call should query database and cache
        $result1 = $this->repository->selectById('unit_001');

        // Create a new unit after first query
        MstUnit::create([
            'id' => 'unit_002',
            'deploy_key' => 202601010,
            'type' => 'Defense',
            'element' => 'Water',
            'rarity' => 'SR',
        ]);

        // Second call should use cache, so unit_002 should not be visible
        $result2 = $this->repository->selectById('unit_002');

        // Assert
        $this->assertNotNull($result1);
        $this->assertEquals('unit_001', $result1->id);
        // unit_002 should not be in cache yet
        $this->assertNull($result2);
    }

    /**
     * Test cache invalidation
     */
    public function test_cache_can_be_cleared(): void
    {
        // Arrange
        MstUnit::create([
            'id' => 'unit_001',
            'deploy_key' => 202601010,
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SSR',
        ]);

        // Act - First call to cache data
        $result1 = $this->repository->selectById('unit_001');

        // Clear Redis cache
        Cache::store('redis')->flush();

        // Create new repository instance to clear internal state
        $newRepository = new MstUnitRepository;

        // Create a new unit
        MstUnit::create([
            'id' => 'unit_002',
            'deploy_key' => 202601010,
            'type' => 'Defense',
            'element' => 'Water',
            'rarity' => 'SR',
        ]);

        // Should see the new unit after cache clear
        $result2 = $newRepository->selectById('unit_002');

        // Assert
        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertEquals('unit_002', $result2->id);
    }

    /**
     * Test selectListByIds with all units
     */
    public function test_select_list_by_ids_returns_all_matching_units(): void
    {
        // Arrange
        MstUnit::create(['id' => 'unit_001', 'deploy_key' => 202601010, 'type' => 'Attack', 'element' => 'Fire', 'rarity' => 'SSR']);
        MstUnit::create(['id' => 'unit_002', 'deploy_key' => 202601010, 'type' => 'Defense', 'element' => 'Water', 'rarity' => 'SR']);
        MstUnit::create(['id' => 'unit_003', 'deploy_key' => 202601010, 'type' => 'Support', 'element' => 'Wind', 'rarity' => 'R']);

        // Act
        $result = $this->repository->selectListByIds(['unit_003', 'unit_001', 'unit_002']);

        // Assert
        $this->assertCount(3, $result);
        $ids = $result->pluck('id')->toArray();
        $this->assertContains('unit_001', $ids);
        $this->assertContains('unit_002', $ids);
        $this->assertContains('unit_003', $ids);
    }

    /**
     * Test different unit types
     */
    public function test_handles_different_unit_types(): void
    {
        // Arrange
        $types = ['Attack', 'Defense', 'Support'];
        $elements = ['Fire', 'Water', 'Wind'];
        $rarities = ['SSR', 'SR', 'R'];

        foreach ($types as $index => $type) {
            MstUnit::create([
                'id' => 'unit_00'.($index + 1),
                'deploy_key' => 202601010,
                'type' => $type,
                'element' => $elements[$index],
                'rarity' => $rarities[$index],
            ]);
        }

        // Act
        $result = $this->repository->selectById('unit_003');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Support', $result->type);
    }

    protected function tearDown(): void
    {
        Cache::store('redis')->flush();
        parent::tearDown();
    }
}
