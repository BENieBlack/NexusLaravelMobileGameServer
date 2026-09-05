<?php

namespace NexusVip\Tests\Unit\Models;

use NexusVip\Models\MstVipLevelReward;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MstVipLevelReward のテスト
 *
 * VIPレベルアップ時に配る報酬のマスター。
 * 実際の配布量は content_quantity × amount で、片方だけ見ると数が合わない。
 */
class MstVipLevelRewardTest extends TestCase
{
    #[Test]
    public function 値を読み出せる(): void
    {
        $reward = $this->makeReward();

        $this->assertSame(5, $reward->getVipLevel());
        $this->assertSame('item', $reward->getContentType());
        $this->assertSame('item_potion', $reward->getContentMstId());
        $this->assertSame(['grade' => 1], $reward->getContentOption());
        $this->assertSame(10, $reward->getContentQuantity());
        $this->assertSame(3, $reward->getAmount());
        $this->assertFalse($reward->getIsPaid());
        $this->assertTrue($reward->isActive());
    }

    #[Test]
    public function 配布量は基本個数と配布回数の積になる(): void
    {
        $this->assertSame(30, $this->makeReward()->getTotalQuantity());
        $this->assertSame(1, $this->makeReward(['content_quantity' => 1, 'amount' => 1])->getTotalQuantity());
    }

    #[Test]
    public function オプションは未設定でもよい(): void
    {
        $this->assertNull($this->makeReward(['content_option' => null])->getContentOption());
    }

    #[Test]
    public function 有償の報酬を表せる(): void
    {
        // ダイヤは有償・無償で残高の枠が分かれる
        $this->assertTrue($this->makeReward(['content_type' => 'diamond', 'is_paid' => true])->getIsPaid());
    }

    #[Test]
    public function 無効な報酬を表せる(): void
    {
        $this->assertFalse($this->makeReward(['is_active' => false])->isActive());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeReward(array $overrides = []): MstVipLevelReward
    {
        return new MstVipLevelReward([
            'vip_level' => 5,
            'content_type' => 'item',
            'content_mst_id' => 'item_potion',
            'content_option' => ['grade' => 1],
            'content_quantity' => 10,
            'amount' => 3,
            'is_paid' => false,
            'sort_order' => 1,
            'is_active' => true,
            ...$overrides,
        ]);
    }
}
