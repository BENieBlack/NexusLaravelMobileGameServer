<?php

namespace NexusVip\Tests\Unit\ValueObjects;

use NexusVip\ValueObjects\VipReward;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VipReward のテスト
 *
 * VIPレベルアップ時に配る報酬1件分。
 * 実際の配布量は content_quantity × amount で、
 * 片方だけを見ると数が合わない。
 */
class VipRewardTest extends TestCase
{
    #[Test]
    public function 値を読み出せる(): void
    {
        $reward = new VipReward('diamond', 'diamond', ['grade' => 1], 100, 3, true);

        $this->assertSame('diamond', $reward->getContentType());
        $this->assertSame('diamond', $reward->getContentMstId());
        $this->assertSame(['grade' => 1], $reward->getContentOption());
        $this->assertSame(100, $reward->getContentQuantity());
        $this->assertSame(3, $reward->getAmount());
        $this->assertTrue($reward->getIsPaid());
    }

    #[Test]
    public function 有償フラグとオプションは省略できる(): void
    {
        $reward = new VipReward('item', 'item_001', null, 1, 1);

        $this->assertNull($reward->getContentOption());
        $this->assertFalse($reward->getIsPaid(), '既定は無償');
    }

    #[Test]
    public function 配布量は基本個数と倍率の積になる(): void
    {
        $this->assertSame(300, (new VipReward('diamond', 'diamond', null, 100, 3))->getTotalQuantity());
        $this->assertSame(1, (new VipReward('item', 'item_001', null, 1, 1))->getTotalQuantity());
    }

    #[Test]
    public function どちらかが0なら配布対象は無い(): void
    {
        // マスターの設定ミスで空の報酬が配送へ流れないようにする
        $this->assertTrue((new VipReward('item', 'item_001', null, 0, 5))->isEmpty());
        $this->assertTrue((new VipReward('item', 'item_001', null, 5, 0))->isEmpty());
        $this->assertFalse((new VipReward('item', 'item_001', null, 1, 1))->isEmpty());
    }

    #[Test]
    public function 報酬タイプが空なら作れない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('報酬タイプは必須です');

        new VipReward('', 'item_001', null, 1, 1);
    }

    #[Test]
    public function 報酬idが空なら作れない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('報酬IDは必須です');

        new VipReward('item', '', null, 1, 1);
    }

    #[Test]
    public function 負の個数や倍率は作れない(): void
    {
        // 0は「配布なし」として許すが、負は表せる意味が無い
        foreach ([[-1, 1, '報酬の基本個数は0以上'], [1, -1, '報酬の倍率は0以上']] as [$quantity, $amount, $message]) {
            try {
                new VipReward('item', 'item_001', null, $quantity, $amount);
                $this->fail("作れてしまった: {$message}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    #[Test]
    public function 同じ内容なら等しい(): void
    {
        $reward = new VipReward('item', 'item_001', ['grade' => 1], 2, 3, true);

        $this->assertTrue($reward->equals(new VipReward('item', 'item_001', ['grade' => 1], 2, 3, true)));
    }

    #[Test]
    public function どれか1つでも違えば等しくない(): void
    {
        $reward = new VipReward('item', 'item_001', ['grade' => 1], 2, 3, true);

        $this->assertFalse($reward->equals(new VipReward('unit', 'item_001', ['grade' => 1], 2, 3, true)));
        $this->assertFalse($reward->equals(new VipReward('item', 'item_002', ['grade' => 1], 2, 3, true)));
        $this->assertFalse($reward->equals(new VipReward('item', 'item_001', null, 2, 3, true)));
        $this->assertFalse($reward->equals(new VipReward('item', 'item_001', ['grade' => 1], 5, 3, true)));
        $this->assertFalse($reward->equals(new VipReward('item', 'item_001', ['grade' => 1], 2, 5, true)));
        $this->assertFalse($reward->equals(new VipReward('item', 'item_001', ['grade' => 1], 2, 3, false)));
    }

    #[Test]
    public function 配列に変換すると配布総量も入る(): void
    {
        $this->assertSame(
            [
                'content_type' => 'diamond',
                'content_mst_id' => 'diamond',
                'content_option' => null,
                'content_quantity' => 100,
                'amount' => 3,
                'total_quantity' => 300,
                'is_paid' => true,
            ],
            (new VipReward('diamond', 'diamond', null, 100, 3, true))->toArray()
        );
    }
}
