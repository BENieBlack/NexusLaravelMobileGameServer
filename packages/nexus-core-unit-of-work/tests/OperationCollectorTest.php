<?php

namespace NexusUnitOfWork\Tests;

use Mockery;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Repositories\_BaseRepository;
use NexusUnitOfWork\Persistence\QueryManager\OperationCollector;
use PHPUnit\Framework\TestCase;

class OperationCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_collect_returns_empty_arrays_when_no_repositories()
    {
        $collector = new OperationCollector;

        $result = $collector->collect([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('inserts', $result);
        $this->assertArrayHasKey('updates', $result);
        $this->assertArrayHasKey('deletes', $result);
        $this->assertEmpty($result['inserts']);
        $this->assertEmpty($result['updates']);
        $this->assertEmpty($result['deletes']);
    }

    public function test_collect_groups_inserts_by_table()
    {
        $collector = new OperationCollector;

        // Create mock models for INSERT
        $model1 = $this->createMockModel(['exists' => false, 'attributes' => ['name' => 'Test1']]);
        $model2 = $this->createMockModel(['exists' => false, 'attributes' => ['name' => 'Test2']]);

        $repository = $this->createMockRepository([
            'connection' => 'mysql',
            'table' => 'test_table',
            'models' => ['key1' => $model1, 'key2' => $model2],
        ]);

        $result = $collector->collect([$repository]);

        $this->assertCount(1, $result['inserts']);
        $this->assertEmpty($result['updates']);
    }

    public function test_collect_identifies_updates()
    {
        $collector = new OperationCollector;

        // Create mock model for UPDATE (exists = true, has dirty attributes)
        $model = $this->createMockModel([
            'exists' => true,
            'attributes' => ['id' => 1, 'name' => 'Updated'],
            'dirty' => ['name' => 'Updated'],
        ]);

        $repository = $this->createMockRepository([
            'connection' => 'mysql',
            'table' => 'test_table',
            'models' => ['key1' => $model],
        ]);

        $result = $collector->collect([$repository]);

        $this->assertEmpty($result['inserts']);
        $this->assertCount(1, $result['updates']);
    }

    public function test_collect_skips_model_without_dirty_attributes()
    {
        $collector = new OperationCollector;

        // Create mock model that exists but has no dirty attributes
        $model = $this->createMockModel([
            'exists' => true,
            'attributes' => ['id' => 1, 'name' => 'Test'],
            'dirty' => [],
        ]);

        $repository = $this->createMockRepository([
            'connection' => 'mysql',
            'table' => 'test_table',
            'models' => ['key1' => $model],
        ]);

        $result = $collector->collect([$repository]);

        $this->assertEmpty($result['inserts']);
        $this->assertEmpty($result['updates']);
    }

    /**
     * Create a mock repository for testing
     *
     * @param  array  $properties
     * @return _BaseRepository
     */
    private function createMockRepository(array $properties = []): _BaseRepository
    {
        $repository = Mockery::mock(_BaseRepository::class);

        $connection = $properties['connection'] ?? 'mysql';
        $table = $properties['table'] ?? 'test_table';
        $models = $properties['models'] ?? [];

        $repository->shouldReceive('getConnection')->andReturn($connection);
        $repository->shouldReceive('getTableName')->andReturn($table);
        $repository->shouldReceive('getQueuedModels')->andReturn($models);
        $repository->shouldReceive('getQueuedDeleteModels')->andReturn([]);
        $repository->shouldReceive('getOriginalStates')->andReturn([]);

        return $repository;
    }

    /**
     * Create a mock model for testing
     *
     * @param  array  $properties
     * @return _BaseModel
     */
    private function createMockModel(array $properties = []): _BaseModel
    {
        $model = Mockery::mock(_BaseModel::class);

        $exists = $properties['exists'] ?? false;
        $attributes = $properties['attributes'] ?? [];
        $dirty = $properties['dirty'] ?? [];

        $model->exists = $exists;

        $model->shouldReceive('getAttributes')->andReturn($attributes);
        $model->shouldReceive('getDirty')->andReturn($dirty);

        return $model;
    }
}
