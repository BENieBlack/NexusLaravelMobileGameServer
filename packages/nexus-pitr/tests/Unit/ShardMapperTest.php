<?php

namespace NexusPitr\Tests\Unit;

use NexusPitr\Logger\ShardMapper;
use PHPUnit\Framework\TestCase;

/**
 * ShardMapperTest
 * 
 * 動的シャーディング対応のテスト
 * DB_TRX_SHARDS環境変数をモック（デフォルト: 2）
 */
class ShardMapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // DB_TRX_SHARDSのデフォルト値を2に設定（テスト環境）
        if (!getenv('DB_TRX_SHARDS')) {
            putenv('DB_TRX_SHARDS=2');
        }
    }
    
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
    public function getLogConnection_supports_dynamic_shards(): void
    {
        // DB_TRX_SHARDS=4の場合
        putenv('DB_TRX_SHARDS=4');
        
        $this->assertEquals('log3', ShardMapper::getLogConnection('trx3'));
        $this->assertEquals('log4', ShardMapper::getLogConnection('trx4'));
        
        // 元に戻す
        putenv('DB_TRX_SHARDS=2');
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
    public function getLogConnection_throws_exception_for_out_of_range_shard(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown trx connection: trx99');
        
        ShardMapper::getLogConnection('trx99');
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
    public function getTrxConnection_supports_dynamic_shards(): void
    {
        // DB_TRX_SHARDS=4の場合
        putenv('DB_TRX_SHARDS=4');
        
        $this->assertEquals('trx3', ShardMapper::getTrxConnection('log3'));
        $this->assertEquals('trx4', ShardMapper::getTrxConnection('log4'));
        
        // 元に戻す
        putenv('DB_TRX_SHARDS=2');
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
    public function getAllLogConnections_returns_dynamic_shards(): void
    {
        // DB_TRX_SHARDS=4の場合
        putenv('DB_TRX_SHARDS=4');
        
        $result = ShardMapper::getAllLogConnections();
        
        $this->assertEquals(['log1', 'log2', 'log3', 'log4'], $result);
        
        // 元に戻す
        putenv('DB_TRX_SHARDS=2');
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
    public function getAllTrxConnections_returns_dynamic_shards(): void
    {
        // DB_TRX_SHARDS=4の場合
        putenv('DB_TRX_SHARDS=4');
        
        $result = ShardMapper::getAllTrxConnections();
        
        $this->assertEquals(['trx1', 'trx2', 'trx3', 'trx4'], $result);
        
        // 元に戻す
        putenv('DB_TRX_SHARDS=2');
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
    public function isValidTrxConnection_validates_dynamic_shards(): void
    {
        // DB_TRX_SHARDS=4の場合
        putenv('DB_TRX_SHARDS=4');
        
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx3'));
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx4'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('trx5'));
        
        // 元に戻す
        putenv('DB_TRX_SHARDS=2');
    }

    /**
     * @test
     */
    public function isValidTrxConnection_returns_false_for_invalid_connection(): void
    {
        $this->assertFalse(ShardMapper::isValidTrxConnection('invalid'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('log1'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('trx99'));
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
    public function isValidLogConnection_validates_dynamic_shards(): void
    {
        // DB_TRX_SHARDS=4の場合
        putenv('DB_TRX_SHARDS=4');
        
        $this->assertTrue(ShardMapper::isValidLogConnection('log3'));
        $this->assertTrue(ShardMapper::isValidLogConnection('log4'));
        $this->assertFalse(ShardMapper::isValidLogConnection('log5'));
        
        // 元に戻す
        putenv('DB_TRX_SHARDS=2');
    }

    /**
     * @test
     */
    public function isValidLogConnection_returns_false_for_invalid_connection(): void
    {
        $this->assertFalse(ShardMapper::isValidLogConnection('invalid'));
        $this->assertFalse(ShardMapper::isValidLogConnection('trx1'));
        $this->assertFalse(ShardMapper::isValidLogConnection('log99'));
    }
}

