<?php

namespace NexusPitr\Tests\Unit;

use NexusPitr\Logger\ShardMapper;
use PHPUnit\Framework\TestCase;

class ShardMapperTest extends TestCase
{
    /**
     * @test
     */
    public function getLogConnection_returns_log1_for_trx1(): void
    {
        $result = ShardMapper::getLogConnection('trx1');
        
        $this->assertEquals('log1', $result);
    }

    /**
     * @test
     */
    public function getLogConnection_returns_log2_for_trx2(): void
    {
        $result = ShardMapper::getLogConnection('trx2');
        
        $this->assertEquals('log2', $result);
    }

    /**
     * @test
     */
    public function getLogConnection_returns_log_for_trx(): void
    {
        $result = ShardMapper::getLogConnection('trx');
        
        $this->assertEquals('log', $result);
    }

    /**
     * @test
     */
    public function getLogConnection_throws_exception_for_invalid_connection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown trx connection: invalid');
        
        ShardMapper::getLogConnection('invalid');
    }

    /**
     * @test
     */
    public function getTrxConnection_returns_trx1_for_log1(): void
    {
        $result = ShardMapper::getTrxConnection('log1');
        
        $this->assertEquals('trx1', $result);
    }

    /**
     * @test
     */
    public function getTrxConnection_returns_trx2_for_log2(): void
    {
        $result = ShardMapper::getTrxConnection('log2');
        
        $this->assertEquals('trx2', $result);
    }

    /**
     * @test
     */
    public function getTrxConnection_returns_trx_for_log(): void
    {
        $result = ShardMapper::getTrxConnection('log');
        
        $this->assertEquals('trx', $result);
    }

    /**
     * @test
     */
    public function getTrxConnection_throws_exception_for_invalid_connection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown log connection: invalid');
        
        ShardMapper::getTrxConnection('invalid');
    }

    /**
     * @test
     */
    public function getAllLogConnections_returns_all_log_connections(): void
    {
        $result = ShardMapper::getAllLogConnections();
        
        $this->assertEquals(['log1', 'log2'], $result);
    }

    /**
     * @test
     */
    public function getAllTrxConnections_returns_all_trx_connections(): void
    {
        $result = ShardMapper::getAllTrxConnections();
        
        $this->assertEquals(['trx1', 'trx2'], $result);
    }

    /**
     * @test
     */
    public function isValidTrxConnection_returns_true_for_valid_connections(): void
    {
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx1'));
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx2'));
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx'));
    }

    /**
     * @test
     */
    public function isValidTrxConnection_returns_false_for_invalid_connection(): void
    {
        $this->assertFalse(ShardMapper::isValidTrxConnection('invalid'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('log1'));
    }

    /**
     * @test
     */
    public function isValidLogConnection_returns_true_for_valid_connections(): void
    {
        $this->assertTrue(ShardMapper::isValidLogConnection('log1'));
        $this->assertTrue(ShardMapper::isValidLogConnection('log2'));
        $this->assertTrue(ShardMapper::isValidLogConnection('log'));
    }

    /**
     * @test
     */
    public function isValidLogConnection_returns_false_for_invalid_connection(): void
    {
        $this->assertFalse(ShardMapper::isValidLogConnection('invalid'));
        $this->assertFalse(ShardMapper::isValidLogConnection('trx1'));
    }
}
