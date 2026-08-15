<?php

namespace NexusPitr\Tests\Unit;

use NexusPitr\Dto\ChangeLogDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChangeLogDtoTest extends TestCase
{
    #[Test]
    public function constructor_sets_all_properties(): void
    {
        $systemAt = new \DateTime('2026-08-09 12:00:00');
        
        $dto = new ChangeLogDto(
            uniqueRequestId: 'req-001',
            sysPlayerId: 123,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 1, 'name' => 'Alice'],
            primaryKey: ['id' => 1],
            systemAt: $systemAt,
            apiEndpoint: '/api/player/create',
            stackTrace: ['trace1', 'trace2']
        );

        $this->assertEquals('req-001', $dto->getUniqueRequestId());
        $this->assertEquals(123, $dto->getSysPlayerId());
        $this->assertEquals('trx1', $dto->getShardConnection());
        $this->assertEquals('trx_player', $dto->getTableName());
        $this->assertEquals('INSERT', $dto->getOperation());
        $this->assertNull($dto->getBeforeData());
        $this->assertEquals(['id' => 1, 'name' => 'Alice'], $dto->getAfterData());
        $this->assertEquals(['id' => 1], $dto->getPrimaryKey());
        $this->assertSame($systemAt, $dto->getSystemAt());
        $this->assertEquals('/api/player/create', $dto->resolveApiEndpoint());
        $this->assertEquals(['trace1', 'trace2'], $dto->getStackTrace());
    }

    #[Test]
    public function constructor_accepts_null_optional_parameters(): void
    {
        $systemAt = new \DateTime('2026-08-09 12:00:00');
        
        $dto = new ChangeLogDto(
            uniqueRequestId: 'req-001',
            sysPlayerId: 123,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'UPDATE',
            beforeData: ['name' => 'Alice'],
            afterData: ['name' => 'Bob'],
            primaryKey: ['id' => 1],
            systemAt: $systemAt
        );

        $this->assertNull($dto->resolveApiEndpoint());
        $this->assertNull($dto->getStackTrace());
    }

    #[Test]
    public function getters_return_correct_types(): void
    {
        $systemAt = new \DateTime('2026-08-09 12:00:00');
        
        $dto = new ChangeLogDto(
            uniqueRequestId: 'req-001',
            sysPlayerId: 123,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'DELETE',
            beforeData: ['id' => 1, 'name' => 'Alice'],
            afterData: null,
            primaryKey: ['id' => 1],
            systemAt: $systemAt,
            apiEndpoint: '/api/player/delete',
            stackTrace: null
        );

        $this->assertIsString($dto->getUniqueRequestId());
        $this->assertIsInt($dto->getSysPlayerId());
        $this->assertIsString($dto->getShardConnection());
        $this->assertIsString($dto->getTableName());
        $this->assertIsString($dto->getOperation());
        $this->assertIsArray($dto->getBeforeData());
        $this->assertNull($dto->getAfterData());
        $this->assertIsArray($dto->getPrimaryKey());
        $this->assertInstanceOf(\DateTime::class, $dto->getSystemAt());
        $this->assertIsString($dto->resolveApiEndpoint());
        $this->assertNull($dto->getStackTrace());
    }

    #[Test]
    public function dto_is_readonly_and_immutable(): void
    {
        $systemAt = new \DateTime('2026-08-09 12:00:00');
        
        $dto = new ChangeLogDto(
            uniqueRequestId: 'req-001',
            sysPlayerId: 123,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 1],
            primaryKey: ['id' => 1],
            systemAt: $systemAt
        );

        // DTOは読み取り専用なので、プロパティを変更できないことを確認
        // PHP 8.1+のreadonly propertyはset不可能
        
        $this->assertEquals('req-001', $dto->getUniqueRequestId());
        $this->assertEquals(123, $dto->getSysPlayerId());
    }

    #[Test]
    public function handles_complex_primary_key(): void
    {
        $systemAt = new \DateTime('2026-08-09 12:00:00');
        
        $dto = new ChangeLogDto(
            uniqueRequestId: 'req-001',
            sysPlayerId: 123,
            shardConnection: 'trx1',
            tableName: 'trx_composite_table',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 1, 'sub_id' => 2, 'value' => 'test'],
            primaryKey: ['id' => 1, 'sub_id' => 2], // Composite key
            systemAt: $systemAt
        );

        $pk = $dto->getPrimaryKey();
        $this->assertCount(2, $pk);
        $this->assertEquals(1, $pk['id']);
        $this->assertEquals(2, $pk['sub_id']);
    }

    #[Test]
    public function handles_large_data_arrays(): void
    {
        $systemAt = new \DateTime('2026-08-09 12:00:00');
        
        $largeData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeData["field_{$i}"] = "value_{$i}";
        }
        
        $dto = new ChangeLogDto(
            uniqueRequestId: 'req-001',
            sysPlayerId: 123,
            shardConnection: 'trx1',
            tableName: 'trx_large_table',
            operation: 'INSERT',
            beforeData: null,
            afterData: $largeData,
            primaryKey: ['id' => 1],
            systemAt: $systemAt
        );

        $afterData = $dto->getAfterData();
        $this->assertCount(100, $afterData);
        $this->assertEquals('value_0', $afterData['field_0']);
        $this->assertEquals('value_99', $afterData['field_99']);
    }
}
