<?php

namespace NexusPitr\Tests\Unit;

use NexusPitr\Logger\TrxChangeLogger;
use NexusPitr\DataTransferObjects\ChangeLog;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Mockery;

class TrxChangeLoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function logBatch_does_nothing_when_empty_array(): void
    {
        DB::shouldReceive('connection')->never();
        
        $logger = new TrxChangeLogger();
        $logger->logBatch([]);
        
        // No exception thrown
        $this->assertTrue(true);
    }

    #[Test]
    public function logBatch_groups_by_log_connection_and_inserts(): void
    {
        // Arrange
        $dto1 = new ChangeLog(
            uniqueRequestId: 'req-001',
            sysPlayerId: 1,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 1, 'name' => 'Alice'],
            primaryKey: ['id' => 1],
            systemAt: new \DateTime('2026-08-09 12:00:00'),
            apiEndpoint: '/api/player/create'
        );

        $dto2 = new ChangeLog(
            uniqueRequestId: 'req-002',
            sysPlayerId: 2,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'UPDATE',
            beforeData: ['name' => 'Bob'],
            afterData: ['name' => 'Bobby'],
            primaryKey: ['id' => 2],
            systemAt: new \DateTime('2026-08-09 12:01:00'),
            apiEndpoint: '/api/player/update'
        );

        // Mock DB connection
        $connectionMock = Mockery::mock();
        $tableMock = Mockery::mock();
        
        DB::shouldReceive('connection')
            ->with('log1')
            ->once()
            ->andReturn($connectionMock);
        
        $connectionMock->shouldReceive('table')
            ->with('log_trx_change')
            ->once()
            ->andReturn($tableMock);
        
        $tableMock->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($records) {
                // Verify 2 records
                $this->assertCount(2, $records);
                
                // Verify first record
                $this->assertEquals('req-001', $records[0]['unique_request_id']);
                $this->assertEquals(1, $records[0]['sys_player_id']);
                $this->assertEquals('trx1', $records[0]['shard_connection']);
                $this->assertEquals('trx_player', $records[0]['table_name']);
                $this->assertEquals('INSERT', $records[0]['operation']);
                $this->assertNull($records[0]['before_data']);
                $this->assertEquals('{"id":1,"name":"Alice"}', $records[0]['after_data']);
                
                // Verify second record
                $this->assertEquals('req-002', $records[1]['unique_request_id']);
                $this->assertEquals('UPDATE', $records[1]['operation']);
                
                return true;
            }));
        
        // Act
        $logger = new TrxChangeLogger();
        $logger->logBatch([$dto1, $dto2]);
    }

    #[Test]
    public function logBatch_handles_multiple_shards(): void
    {
        // Arrange
        $dto1 = new ChangeLog(
            uniqueRequestId: 'req-001',
            sysPlayerId: 1,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 1],
            primaryKey: ['id' => 1],
            systemAt: new \DateTime('2026-08-09 12:00:00'),
            apiEndpoint: '/api/test'
        );

        $dto2 = new ChangeLog(
            uniqueRequestId: 'req-002',
            sysPlayerId: 2,
            shardConnection: 'trx2', // Different shard
            tableName: 'trx_player',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 2],
            primaryKey: ['id' => 2],
            systemAt: new \DateTime('2026-08-09 12:00:00'),
            apiEndpoint: '/api/test'
        );

        // Mock DB connections for log1
        $connectionMock1 = Mockery::mock();
        $tableMock1 = Mockery::mock();
        
        DB::shouldReceive('connection')
            ->with('log1')
            ->once()
            ->andReturn($connectionMock1);
        
        $connectionMock1->shouldReceive('table')
            ->with('log_trx_change')
            ->once()
            ->andReturn($tableMock1);
        
        $tableMock1->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($records) {
                $this->assertCount(1, $records);
                $this->assertEquals('trx1', $records[0]['shard_connection']);
                return true;
            }));

        // Mock DB connections for log2
        $connectionMock2 = Mockery::mock();
        $tableMock2 = Mockery::mock();
        
        DB::shouldReceive('connection')
            ->with('log2')
            ->once()
            ->andReturn($connectionMock2);
        
        $connectionMock2->shouldReceive('table')
            ->with('log_trx_change')
            ->once()
            ->andReturn($tableMock2);
        
        $tableMock2->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($records) {
                $this->assertCount(1, $records);
                $this->assertEquals('trx2', $records[0]['shard_connection']);
                return true;
            }));
        
        // Act
        $logger = new TrxChangeLogger();
        $logger->logBatch([$dto1, $dto2]);
    }

    #[Test]
    public function log_single_record_calls_logBatch(): void
    {
        // Arrange
        $dto = new ChangeLog(
            uniqueRequestId: 'req-001',
            sysPlayerId: 1,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'DELETE',
            beforeData: ['id' => 1, 'name' => 'Alice'],
            afterData: null,
            primaryKey: ['id' => 1],
            systemAt: new \DateTime('2026-08-09 12:00:00'),
            apiEndpoint: '/api/player/delete'
        );

        // Mock
        $connectionMock = Mockery::mock();
        $tableMock = Mockery::mock();
        
        DB::shouldReceive('connection')
            ->with('log1')
            ->once()
            ->andReturn($connectionMock);
        
        $connectionMock->shouldReceive('table')
            ->with('log_trx_change')
            ->once()
            ->andReturn($tableMock);
        
        $tableMock->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($records) {
                $this->assertCount(1, $records);
                $this->assertEquals('DELETE', $records[0]['operation']);
                $this->assertEquals('{"id":1,"name":"Alice"}', $records[0]['before_data']);
                $this->assertNull($records[0]['after_data']);
                return true;
            }));
        
        // Act
        $logger = new TrxChangeLogger();
        $logger->log($dto);
    }

    #[Test]
    public function logBatch_handles_null_data_properly(): void
    {
        // Arrange
        $dto = new ChangeLog(
            uniqueRequestId: 'req-001',
            sysPlayerId: 1,
            shardConnection: 'trx1',
            tableName: 'trx_player',
            operation: 'INSERT',
            beforeData: null,
            afterData: ['id' => 1],
            primaryKey: ['id' => 1],
            systemAt: new \DateTime('2026-08-09 12:00:00'),
            apiEndpoint: null,
            stackTrace: null
        );

        // Mock
        $connectionMock = Mockery::mock();
        $tableMock = Mockery::mock();
        
        DB::shouldReceive('connection')
            ->with('log1')
            ->once()
            ->andReturn($connectionMock);
        
        $connectionMock->shouldReceive('table')
            ->with('log_trx_change')
            ->once()
            ->andReturn($tableMock);
        
        $tableMock->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($records) {
                $this->assertNull($records[0]['before_data']);
                $this->assertNull($records[0]['api_endpoint']);
                $this->assertNull($records[0]['stack_trace']);
                return true;
            }));
        
        // Act
        $logger = new TrxChangeLogger();
        $logger->log($dto);
    }
}
