<?php

namespace LaravelPersistence\Tests;

use LaravelPersistence\Models\_BaseModel;
use PHPUnit\Framework\TestCase;
use Mockery;

class BaseModelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testUsesUnitOfWorkDefaultsToFalse()
    {
        $model = $this->createMockModel();
        $this->assertFalse($model->usesUnitOfWork());
    }

    public function testUsesUnitOfWorkCanBeSetToTrue()
    {
        $model = $this->createMockModel(['usesUnitOfWork' => true]);
        $this->assertTrue($model->usesUnitOfWork());
    }

    public function testGetConnectionName()
    {
        $model = $this->createMockModel(['connection' => 'mysql_test']);
        $this->assertEquals('mysql_test', $model->getConnectionName());
    }

    public function testGetTableName()
    {
        $model = $this->createMockModel(['table' => 'test_table']);
        $this->assertEquals('test_table', $model->getTableName());
    }

    public function testIsNewReturnsTrueForNewModel()
    {
        $model = $this->createMockModel(['exists' => false]);
        $this->assertTrue($model->isNew());
    }

    public function testIsNewReturnsFalseForExistingModel()
    {
        $model = $this->createMockModel(['exists' => true]);
        $this->assertFalse($model->isNew());
    }

    public function testNeedsUpdateReturnsTrueWhenDirty()
    {
        $model = $this->createMockModel(['exists' => true, 'isDirty' => true]);
        $this->assertTrue($model->needsUpdate());
    }

    public function testNeedsUpdateReturnsFalseWhenNotDirty()
    {
        $model = $this->createMockModel(['exists' => true, 'isDirty' => false]);
        $this->assertFalse($model->needsUpdate());
    }

    public function testNeedsUpdateReturnsFalseForNewModel()
    {
        $model = $this->createMockModel(['exists' => false, 'isDirty' => true]);
        $this->assertFalse($model->needsUpdate());
    }

    public function testToDebugArrayReturnsExpectedStructure()
    {
        $model = $this->createMockModel([
            'exists' => true,
            'table' => 'test_table',
            'connection' => 'mysql_test',
            'primaryKey' => 'id',
            'attributes' => ['id' => 1, 'name' => 'Test'],
            'original' => ['id' => 1, 'name' => 'Original'],
            'changes' => ['name' => 'Test'],
            'dirty' => ['name' => 'Test'],
        ]);

        $debugArray = $model->toDebugArray();

        $this->assertIsArray($debugArray);
        $this->assertArrayHasKey('table', $debugArray);
        $this->assertArrayHasKey('connection', $debugArray);
        $this->assertArrayHasKey('primaryKey', $debugArray);
        $this->assertArrayHasKey('exists', $debugArray);
        $this->assertArrayHasKey('attributes', $debugArray);
        $this->assertArrayHasKey('original', $debugArray);
        $this->assertArrayHasKey('changes', $debugArray);
        $this->assertArrayHasKey('dirty', $debugArray);
    }

    /**
     * Create a mock model for testing
     * 
     * @param array $properties
     * @return _BaseModel
     */
    private function createMockModel(array $properties = []): _BaseModel
    {
        $model = Mockery::mock(_BaseModel::class)->makePartial();

        // Set default properties
        $defaults = [
            'usesUnitOfWork' => false,
            'connection' => 'mysql',
            'table' => 'test_table',
            'primaryKey' => 'id',
            'exists' => false,
            'isDirty' => false,
            'attributes' => [],
            'original' => [],
            'changes' => [],
            'dirty' => [],
        ];

        $properties = array_merge($defaults, $properties);

        // Mock protected properties
        if (isset($properties['usesUnitOfWork'])) {
            $reflection = new \ReflectionClass($model);
            $property = $reflection->getProperty('usesUnitOfWork');
            $property->setAccessible(true);
            $property->setValue($model, $properties['usesUnitOfWork']);
        }

        if (isset($properties['connection'])) {
            $reflection = new \ReflectionClass($model);
            $property = $reflection->getProperty('connection');
            $property->setAccessible(true);
            $property->setValue($model, $properties['connection']);
        }

        // Mock public properties
        $model->exists = $properties['exists'];

        // Mock methods
        $model->shouldReceive('getTable')->andReturn($properties['table']);
        $model->shouldReceive('getKeyName')->andReturn($properties['primaryKey']);
        $model->shouldReceive('isDirty')->andReturn($properties['isDirty']);
        $model->shouldReceive('getChanges')->andReturn($properties['changes']);
        $model->shouldReceive('getDirty')->andReturn($properties['dirty']);

        // For toDebugArray test
        if (!empty($properties['attributes'])) {
            $reflection = new \ReflectionClass($model);
            $property = $reflection->getProperty('attributes');
            $property->setAccessible(true);
            $property->setValue($model, $properties['attributes']);
        }

        if (!empty($properties['original'])) {
            $reflection = new \ReflectionClass($model);
            $property = $reflection->getProperty('original');
            $property->setAccessible(true);
            $property->setValue($model, $properties['original']);
        }

        return $model;
    }
}
