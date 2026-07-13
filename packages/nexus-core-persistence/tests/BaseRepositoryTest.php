<?php

namespace LaravelPersistence\Tests;

use LaravelPersistence\Repositories\_BaseRepository;
use LaravelPersistence\Models\_BaseModel;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Mockery;

class BaseRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetModelsReturnsEmptyArrayWhenQueueIsEmpty()
    {
        $repository = $this->createMockRepository();
        
        $models = $repository->getModels();
        
        $this->assertIsArray($models);
        $this->assertEmpty($models);
    }

    public function testGetDeleteModelsReturnsEmptyArrayWhenQueueIsEmpty()
    {
        $repository = $this->createMockRepository();
        
        $models = $repository->getDeleteModels();
        
        $this->assertIsArray($models);
        $this->assertEmpty($models);
    }

    public function testSaveAddsModelToQueue()
    {
        $repository = $this->createMockRepository();
        $model = $this->createMockModel();
        
        $repository->save($model);
        
        $models = $repository->getModels();
        $this->assertCount(1, $models);
        $this->assertSame($model, $models[0]);
    }

    public function testSaveMultipleModelsToQueue()
    {
        $repository = $this->createMockRepository();
        $model1 = $this->createMockModel(['id' => 1]);
        $model2 = $this->createMockModel(['id' => 2]);
        
        $repository->save($model1);
        $repository->save($model2);
        
        $models = $repository->getModels();
        $this->assertCount(2, $models);
    }

    public function testDeleteAddsModelToDeleteQueue()
    {
        $repository = $this->createMockRepository();
        $model = $this->createMockModel(['id' => 1]);
        
        $repository->delete($model);
        
        $deleteModels = $repository->getDeleteModels();
        $this->assertCount(1, $deleteModels);
        $this->assertSame($model, $deleteModels[0]);
    }

    public function testClearModelsResetsQueue()
    {
        $repository = $this->createMockRepository();
        $model = $this->createMockModel();
        
        $repository->save($model);
        $this->assertCount(1, $repository->getModels());
        
        $repository->clearModels();
        $this->assertEmpty($repository->getModels());
    }

    public function testClearDeleteModelsResetsDeleteQueue()
    {
        $repository = $this->createMockRepository();
        $model = $this->createMockModel(['id' => 1]);
        
        $repository->delete($model);
        $this->assertCount(1, $repository->getDeleteModels());
        
        $repository->clearDeleteModels();
        $this->assertEmpty($repository->getDeleteModels());
    }

    public function testGetUniqueKeysReturnsConfiguredKeys()
    {
        $repository = $this->createMockRepository(['uniqueKeys' => ['id']]);
        
        $keys = $repository->getUniqueKeys();
        
        $this->assertIsArray($keys);
        $this->assertEquals(['id'], $keys);
    }

    public function testGetUniqueKeysReturnsCompositeKeys()
    {
        $repository = $this->createMockRepository(['uniqueKeys' => ['player_id', 'item_id']]);
        
        $keys = $repository->getUniqueKeys();
        
        $this->assertIsArray($keys);
        $this->assertEquals(['player_id', 'item_id'], $keys);
    }

    public function testGetConnectionNameReturnsConfiguredConnection()
    {
        $repository = $this->createMockRepository(['connection' => 'mysql_test']);
        
        $connection = $repository->getConnectionName();
        
        $this->assertEquals('mysql_test', $connection);
    }

    /**
     * Create a mock repository for testing
     * 
     * @param array $properties
     * @return _BaseRepository
     */
    private function createMockRepository(array $properties = []): _BaseRepository
    {
        $repository = Mockery::mock(_BaseRepository::class)->makePartial();
        
        // Set default properties
        $defaults = [
            'modelClass' => _BaseModel::class,
            'uniqueKeys' => ['id'],
            'connection' => 'mysql',
        ];
        
        $properties = array_merge($defaults, $properties);
        
        // Use reflection to set protected properties
        $reflection = new \ReflectionClass($repository);
        
        if (isset($properties['modelClass'])) {
            $property = $reflection->getProperty('modelClass');
            $property->setAccessible(true);
            $property->setValue($repository, $properties['modelClass']);
        }
        
        if (isset($properties['uniqueKeys'])) {
            $property = $reflection->getProperty('uniqueKeys');
            $property->setAccessible(true);
            $property->setValue($repository, $properties['uniqueKeys']);
        }
        
        if (isset($properties['connection'])) {
            $property = $reflection->getProperty('connection');
            $property->setAccessible(true);
            $property->setValue($repository, $properties['connection']);
        }
        
        return $repository;
    }

    /**
     * Create a mock model for testing
     * 
     * @param array $attributes
     * @return _BaseModel
     */
    private function createMockModel(array $attributes = []): _BaseModel
    {
        $model = Mockery::mock(_BaseModel::class);
        
        foreach ($attributes as $key => $value) {
            $model->$key = $value;
        }
        
        return $model;
    }
}
