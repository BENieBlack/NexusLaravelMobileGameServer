<?php

namespace NexusPitr\Tests\Unit;

use NexusPitr\Logger\ShardMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ShardMapperTest
 *
 * 動的シャーディング対応のテスト
 * DB_SHARD_COUNT環境変数をモック（デフォルト: 2）
 */
class ShardMapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // DB_SHARD_COUNTのデフォルト値を2に設定（テスト環境）
        if (! getenv('DB_SHARD_COUNT')) {
            putenv('DB_SHARD_COUNT=2');
        }
    }

    #[Test]
    public function get_log_connection_returns_log1_for_trx1(): void
    {
        $result = ShardMapper::resolveLogConnection('trx1');

        $this->assertEquals('log1', $result);
    }

    #[Test]
    public function get_log_connection_returns_log2_for_trx2(): void
    {
        $result = ShardMapper::resolveLogConnection('trx2');

        $this->assertEquals('log2', $result);
    }

    #[Test]
    public function get_log_connection_supports_dynamic_shards(): void
    {
        // DB_SHARD_COUNT=4の場合
        putenv('DB_SHARD_COUNT=4');

        $this->assertEquals('log3', ShardMapper::resolveLogConnection('trx3'));
        $this->assertEquals('log4', ShardMapper::resolveLogConnection('trx4'));

        // 元に戻す
        putenv('DB_SHARD_COUNT=2');
    }

    #[Test]
    public function get_log_connection_throws_exception_for_invalid_connection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown trx connection: invalid');

        ShardMapper::resolveLogConnection('invalid');
    }

    #[Test]
    public function get_log_connection_throws_exception_for_out_of_range_shard(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown trx connection: trx99');

        ShardMapper::resolveLogConnection('trx99');
    }

    #[Test]
    public function get_trx_connection_returns_trx1_for_log1(): void
    {
        $result = ShardMapper::resolveTrxConnection('log1');

        $this->assertEquals('trx1', $result);
    }

    #[Test]
    public function get_trx_connection_returns_trx2_for_log2(): void
    {
        $result = ShardMapper::resolveTrxConnection('log2');

        $this->assertEquals('trx2', $result);
    }

    #[Test]
    public function get_trx_connection_supports_dynamic_shards(): void
    {
        // DB_SHARD_COUNT=4の場合
        putenv('DB_SHARD_COUNT=4');

        $this->assertEquals('trx3', ShardMapper::resolveTrxConnection('log3'));
        $this->assertEquals('trx4', ShardMapper::resolveTrxConnection('log4'));

        // 元に戻す
        putenv('DB_SHARD_COUNT=2');
    }

    #[Test]
    public function get_trx_connection_throws_exception_for_invalid_connection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown log connection: invalid');

        ShardMapper::resolveTrxConnection('invalid');
    }

    #[Test]
    public function get_all_log_connections_returns_all_log_connections(): void
    {
        $result = ShardMapper::allLogConnections();

        $this->assertEquals(['log1', 'log2'], $result);
    }

    #[Test]
    public function get_all_log_connections_returns_dynamic_shards(): void
    {
        // DB_SHARD_COUNT=4の場合
        putenv('DB_SHARD_COUNT=4');

        $result = ShardMapper::allLogConnections();

        $this->assertEquals(['log1', 'log2', 'log3', 'log4'], $result);

        // 元に戻す
        putenv('DB_SHARD_COUNT=2');
    }

    #[Test]
    public function get_all_trx_connections_returns_all_trx_connections(): void
    {
        $result = ShardMapper::allTrxConnections();

        $this->assertEquals(['trx1', 'trx2'], $result);
    }

    #[Test]
    public function get_all_trx_connections_returns_dynamic_shards(): void
    {
        // DB_SHARD_COUNT=4の場合
        putenv('DB_SHARD_COUNT=4');

        $result = ShardMapper::allTrxConnections();

        $this->assertEquals(['trx1', 'trx2', 'trx3', 'trx4'], $result);

        // 元に戻す
        putenv('DB_SHARD_COUNT=2');
    }

    #[Test]
    public function is_valid_trx_connection_returns_true_for_valid_connections(): void
    {
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx1'));
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx2'));
    }

    #[Test]
    public function is_valid_trx_connection_validates_dynamic_shards(): void
    {
        // DB_SHARD_COUNT=4の場合
        putenv('DB_SHARD_COUNT=4');

        $this->assertTrue(ShardMapper::isValidTrxConnection('trx3'));
        $this->assertTrue(ShardMapper::isValidTrxConnection('trx4'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('trx5'));

        // 元に戻す
        putenv('DB_SHARD_COUNT=2');
    }

    #[Test]
    public function is_valid_trx_connection_returns_false_for_invalid_connection(): void
    {
        $this->assertFalse(ShardMapper::isValidTrxConnection('invalid'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('log1'));
        $this->assertFalse(ShardMapper::isValidTrxConnection('trx99'));
    }

    #[Test]
    public function is_valid_log_connection_returns_true_for_valid_connections(): void
    {
        $this->assertTrue(ShardMapper::isValidLogConnection('log1'));
        $this->assertTrue(ShardMapper::isValidLogConnection('log2'));
    }

    #[Test]
    public function is_valid_log_connection_validates_dynamic_shards(): void
    {
        // DB_SHARD_COUNT=4の場合
        putenv('DB_SHARD_COUNT=4');

        $this->assertTrue(ShardMapper::isValidLogConnection('log3'));
        $this->assertTrue(ShardMapper::isValidLogConnection('log4'));
        $this->assertFalse(ShardMapper::isValidLogConnection('log5'));

        // 元に戻す
        putenv('DB_SHARD_COUNT=2');
    }

    #[Test]
    public function is_valid_log_connection_returns_false_for_invalid_connection(): void
    {
        $this->assertFalse(ShardMapper::isValidLogConnection('invalid'));
        $this->assertFalse(ShardMapper::isValidLogConnection('trx1'));
        $this->assertFalse(ShardMapper::isValidLogConnection('log99'));
    }
}
