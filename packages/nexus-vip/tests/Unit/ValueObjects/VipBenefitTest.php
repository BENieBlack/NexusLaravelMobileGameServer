<?php

namespace NexusVip\Tests\Unit\ValueObjects;

use NexusVip\ValueObjects\VipBenefit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VipBenefit のテスト
 *
 * VIPレベルごとの特典。割引の計算は特典そのものの振る舞いとして
 * ここが持つため、価格に直結する。端数と下限の扱いが要点。
 */
class VipBenefitTest extends TestCase
{
    #[Test]
    public function 値を読み出せる(): void
    {
        $benefit = new VipBenefit(50, 30, 0.1, 0.2);

        $this->assertSame(50, $benefit->getMaxStaminaBonus());
        $this->assertSame(30, $benefit->calcDailyDiamondBonus());
        $this->assertSame(0.1, $benefit->getShopDiscountRate());
        $this->assertSame(0.2, $benefit->getGachaDiscountRate());
    }

    #[Test]
    public function 特典なしを作れる(): void
    {
        $benefit = VipBenefit::none();

        $this->assertSame(0, $benefit->getMaxStaminaBonus());
        $this->assertSame(0, $benefit->calcDailyDiamondBonus());
        $this->assertSame(0.0, $benefit->getShopDiscountRate());
        $this->assertSame(0.0, $benefit->getGachaDiscountRate());
    }

    // ========================================
    // 生成時の検証
    // ========================================

    #[Test]
    public function 負のボーナスは作れない(): void
    {
        $this->assertInvalid(
            fn () => new VipBenefit(-1, 0, 0.0, 0.0),
            'スタミナ上限ボーナスは0以上'
        );

        $this->assertInvalid(
            fn () => new VipBenefit(0, -1, 0.0, 0.0),
            'デイリーダイヤモンドボーナスは0以上'
        );
    }

    #[Test]
    public function 割引率は0から1の範囲に限る(): void
    {
        // 1.0を超えると価格が負になり、負だと値上げになる
        $this->assertInvalid(fn () => new VipBenefit(0, 0, 1.01, 0.0), 'ショップ割引率は0.0〜1.0');
        $this->assertInvalid(fn () => new VipBenefit(0, 0, -0.01, 0.0), 'ショップ割引率は0.0〜1.0');
        $this->assertInvalid(fn () => new VipBenefit(0, 0, 0.0, 1.01), 'ガチャ割引率は0.0〜1.0');
        $this->assertInvalid(fn () => new VipBenefit(0, 0, 0.0, -0.01), 'ガチャ割引率は0.0〜1.0');
    }

    #[Test]
    public function 境界の0と1は使える(): void
    {
        $this->assertSame(0.0, (new VipBenefit(0, 0, 0.0, 0.0))->getShopDiscountRate());
        $this->assertSame(1.0, (new VipBenefit(0, 0, 1.0, 1.0))->getShopDiscountRate());
    }

    // ========================================
    // 特典の適用
    // ========================================

    #[Test]
    public function スタミナ上限に加算する(): void
    {
        $this->assertSame(150, (new VipBenefit(50, 0, 0.0, 0.0))->applyStaminaBonus(100));
        $this->assertSame(100, VipBenefit::none()->applyStaminaBonus(100));
    }

    #[Test]
    public function ショップとガチャの割引はそれぞれの率で効く(): void
    {
        $benefit = new VipBenefit(0, 0, 0.1, 0.5);

        $this->assertSame(900, $benefit->applyShopDiscount(1000));
        $this->assertSame(500, $benefit->applyGachaDiscount(1000));
    }

    #[Test]
    public function 割引の端数は切り捨てる(): void
    {
        // 1000 - 1000×0.15 = 850、333 - 333×0.1 = 299.7 → 299
        $benefit = new VipBenefit(0, 0, 0.15, 0.1);

        $this->assertSame(850, $benefit->applyShopDiscount(1000));
        $this->assertSame(299, $benefit->applyGachaDiscount(333));
    }

    #[Test]
    public function 割引後の価格は1を下回らない(): void
    {
        // 全額割引でも0円にはしない。無料配布は別の仕組みで扱う
        $full = new VipBenefit(0, 0, 1.0, 1.0);

        $this->assertSame(1, $full->applyShopDiscount(1000));
        $this->assertSame(1, $full->applyGachaDiscount(1));
    }

    #[Test]
    public function 割引なしなら価格は変わらない(): void
    {
        $this->assertSame(1000, VipBenefit::none()->applyShopDiscount(1000));
        $this->assertSame(1000, VipBenefit::none()->applyGachaDiscount(1000));
    }

    // ========================================
    // 比較と変換
    // ========================================

    #[Test]
    public function 同じ内容なら等しい(): void
    {
        $benefit = new VipBenefit(50, 30, 0.1, 0.2);

        $this->assertTrue($benefit->equals(new VipBenefit(50, 30, 0.1, 0.2)));
    }

    #[Test]
    public function どれか1つでも違えば等しくない(): void
    {
        $benefit = new VipBenefit(50, 30, 0.1, 0.2);

        $this->assertFalse($benefit->equals(new VipBenefit(51, 30, 0.1, 0.2)));
        $this->assertFalse($benefit->equals(new VipBenefit(50, 31, 0.1, 0.2)));
        $this->assertFalse($benefit->equals(new VipBenefit(50, 30, 0.2, 0.2)));
        $this->assertFalse($benefit->equals(new VipBenefit(50, 30, 0.1, 0.3)));
    }

    #[Test]
    public function 配列に変換できる(): void
    {
        $this->assertSame(
            [
                'max_stamina_bonus' => 50,
                'daily_diamond_bonus' => 30,
                'shop_discount_rate' => 0.1,
                'gacha_discount_rate' => 0.2,
            ],
            (new VipBenefit(50, 30, 0.1, 0.2))->toArray()
        );
    }

    private function assertInvalid(callable $make, string $expectedMessage): void
    {
        try {
            $make();
            $this->fail("作れてしまった: {$expectedMessage}");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }
}
