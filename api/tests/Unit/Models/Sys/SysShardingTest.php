<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysSharding;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysSharding のテスト
 *
 * シャーディング構成の定義。分散戦略の判定とアクセサを確認する。
 */
class SysShardingTest extends TestCase
{
    #[Test]
    public function 分散戦略を判定できる(): void
    {
        $hash = $this->makeSharding(['strategy' => SysSharding::STRATEGY_HASH]);
        $this->assertTrue($hash->isHashStrategy());
        $this->assertFalse($hash->isRangeStrategy());
        $this->assertFalse($hash->isConsistentStrategy());

        $this->assertTrue($this->makeSharding(['strategy' => SysSharding::STRATEGY_RANGE])->isRangeStrategy());
        $this->assertTrue($this->makeSharding(['strategy' => SysSharding::STRATEGY_CONSISTENT])->isConsistentStrategy());
    }

    #[Test]
    public function 有効かどうかを判定できる(): void
    {
        $this->assertTrue($this->makeSharding(['is_active' => true])->isActive());
        $this->assertFalse($this->makeSharding(['is_active' => false])->isActive());
    }

    #[Test]
    public function レスポンスではidをsys_sharding_idに置き換える(): void
    {
        $sharding = $this->makeSharding();
        $sharding->id = 3;

        $array = $sharding->toResponseArray();

        $this->assertSame(3, $array['sys_sharding_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    #[Test]
    public function アクセサで値を出し入れできる(): void
    {
        $sharding = new SysSharding;
        $sharding->setName('trx-sharding');
        $sharding->setTarget(SysSharding::TARGET_TRANSACTION);
        $sharding->setStrategy(SysSharding::STRATEGY_HASH);
        $sharding->setShardingKey('sys_player_id');
        $sharding->setNodeCount(3);
        $sharding->setDescription('トランザクションDBの分散');

        $this->assertSame('trx-sharding', $sharding->getName());
        $this->assertSame(SysSharding::TARGET_TRANSACTION, $sharding->getTarget());
        $this->assertSame(SysSharding::STRATEGY_HASH, $sharding->getStrategy());
        $this->assertSame('sys_player_id', $sharding->getShardingKey());
        $this->assertSame(3, $sharding->getNodeCount());
        $this->assertSame('トランザクションDBの分散', $sharding->getDescription());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSharding(array $attributes = []): SysSharding
    {
        return new SysSharding(array_merge([
            'name' => 'trx-sharding',
            'target' => SysSharding::TARGET_TRANSACTION,
            'strategy' => SysSharding::STRATEGY_HASH,
            'sharding_key' => 'sys_player_id',
            'node_count' => 3,
            'is_active' => true,
        ], $attributes));
    }
}
