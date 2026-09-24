<?php

namespace NexusGacha\Tests\Unit\ValueObjects;

use NexusGacha\ValueObjects\GachaPrize;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GachaPrize のテスト
 *
 * 抽選結果を表す値オブジェクト。付与処理へそのまま渡るため、
 * 中身が空・数量0といった作れてはいけない状態を弾く。
 */
class GachaPrizeTest extends TestCase
{
    #[Test]
    public function 値を読み出せる(): void
    {
        $prize = new GachaPrize('Unit', 'unit_ssr_001', 2, 5, true);

        $this->assertSame('Unit', $prize->getContentType());
        $this->assertSame('unit_ssr_001', $prize->getContentMstId());
        $this->assertSame(2, $prize->getAmount());
        $this->assertSame(5, $prize->getRarity());
        $this->assertTrue($prize->isGuaranteed());
    }

    #[Test]
    public function 景品タイプが空なら作れない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('景品タイプは必須です');

        new GachaPrize('', 'unit_001', 1, 1, false);
    }

    #[Test]
    public function 景品idが空なら作れない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('景品IDは必須です');

        new GachaPrize('Unit', '', 1, 1, false);
    }

    #[Test]
    public function 獲得数が0以下なら作れない(): void
    {
        // 0個の景品が付与処理まで流れると、何も付かないのに引いた扱いになる
        foreach ([0, -1] as $amount) {
            try {
                new GachaPrize('Unit', 'unit_001', $amount, 1, false);
                $this->fail("獲得数 {$amount} で作れてしまった");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('獲得数は1以上', $e->getMessage());
            }
        }
    }

    #[Test]
    public function レアリティが負なら作れない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('レアリティは0以上');

        new GachaPrize('Unit', 'unit_001', 1, -1, false);
    }

    #[Test]
    public function レアリティ0は作れる(): void
    {
        // レアリティを持たない景品（通貨など）を表せる
        $this->assertSame(0, (new GachaPrize('Gold', 'gold', 100, 0, false))->getRarity());
    }

    #[Test]
    public function 指定レアリティ以上かを判定できる(): void
    {
        $prize = new GachaPrize('Unit', 'unit_001', 1, 4, false);

        $this->assertTrue($prize->isAtLeastRarity(3));
        $this->assertTrue($prize->isAtLeastRarity(4), '同じレアリティも含む');
        $this->assertFalse($prize->isAtLeastRarity(5));
    }

    #[Test]
    public function 同じ内容なら等しい(): void
    {
        $prize = new GachaPrize('Unit', 'unit_001', 1, 5, true);

        $this->assertTrue($prize->equals(new GachaPrize('Unit', 'unit_001', 1, 5, true)));
    }

    #[Test]
    public function どれか1つでも違えば等しくない(): void
    {
        $prize = new GachaPrize('Unit', 'unit_001', 1, 5, true);

        $this->assertFalse($prize->equals(new GachaPrize('Item', 'unit_001', 1, 5, true)));
        $this->assertFalse($prize->equals(new GachaPrize('Unit', 'unit_002', 1, 5, true)));
        $this->assertFalse($prize->equals(new GachaPrize('Unit', 'unit_001', 2, 5, true)));
        $this->assertFalse($prize->equals(new GachaPrize('Unit', 'unit_001', 1, 4, true)));
        $this->assertFalse($prize->equals(new GachaPrize('Unit', 'unit_001', 1, 5, false)));
    }

    #[Test]
    public function 配列に変換できる(): void
    {
        // 余計なキーが混ざらないよう、配列ごと突き合わせる
        $this->assertSame(
            [
                'content_type' => 'Unit',
                'content_mst_id' => 'unit_ssr_001',
                'amount' => 2,
                'rarity' => 5,
                'is_guaranteed' => true,
            ],
            (new GachaPrize('Unit', 'unit_ssr_001', 2, 5, true))->toArray()
        );

        $this->assertFalse((new GachaPrize('Equipment', 'equip_001', 1, 4, false))->toArray()['is_guaranteed']);
    }
}
