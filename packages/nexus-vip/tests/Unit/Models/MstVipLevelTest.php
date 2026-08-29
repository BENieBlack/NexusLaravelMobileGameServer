<?php

namespace NexusVip\Tests\Unit\Models;

use NexusVip\Models\MstVipLevel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MstVipLevel のテスト
 *
 * VIPレベルのマスター。割引率が decimal:2 で文字列として入るため、
 * getter が数値へ寄せているかが要点。ここがずれると価格計算に響く。
 */
class MstVipLevelTest extends TestCase
{
    #[Test]
    public function 値を読み出せる(): void
    {
        $level = $this->makeLevel();

        $this->assertSame(5, $level->getLevel());
        $this->assertSame(10000, $level->getRequiredPoint());
        $this->assertSame(50, $level->getMaxStaminaBonus());
        $this->assertSame(30, $level->calcDailyDiamondBonus());
        $this->assertSame('https://example.com/badge_5.png', $level->getDisplayBadgeUrl());
        $this->assertTrue($level->isActive());
    }

    #[Test]
    public function 割引率は数値で返る(): void
    {
        // decimal:2 は文字列で入るため、素で返すと計算に使えない
        $level = $this->makeLevel();

        $this->assertSame(0.1, $level->getShopDiscountRate());
        $this->assertSame(0.25, $level->getGachaDiscountRate());
    }

    #[Test]
    public function バッジは未設定でもよい(): void
    {
        $this->assertNull($this->makeLevel(['display_badge_url' => null])->getDisplayBadgeUrl());
    }

    #[Test]
    public function 無効なレベルを表せる(): void
    {
        $this->assertFalse($this->makeLevel(['is_active' => false])->isActive());
    }

    #[Test]
    public function vip0は必要ポイントも特典も0(): void
    {
        $level = $this->makeLevel([
            'level' => 0,
            'required_point' => 0,
            'max_stamina_bonus' => 0,
            'daily_diamond_bonus' => 0,
            'shop_discount_rate' => 0,
            'gacha_discount_rate' => 0,
        ]);

        $this->assertSame(0, $level->getLevel());
        $this->assertSame(0, $level->getRequiredPoint());
        $this->assertSame(0.0, $level->getShopDiscountRate());
    }

    #[Test]
    public function レスポンス用の配列に変換できる(): void
    {
        $this->assertSame(
            [
                'level' => 5,
                'required_point' => 10000,
                'benefits' => [
                    'max_stamina_bonus' => 50,
                    'daily_diamond_bonus' => 30,
                    'shop_discount_rate' => 0.1,
                    'gacha_discount_rate' => 0.25,
                ],
                'display_badge_url' => 'https://example.com/badge_5.png',
            ],
            $this->makeLevel()->toResponseArray()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLevel(array $overrides = []): MstVipLevel
    {
        return new MstVipLevel([
            'id' => 'vip_5',
            'level' => 5,
            'required_point' => 10000,
            'max_stamina_bonus' => 50,
            'daily_diamond_bonus' => 30,
            'shop_discount_rate' => 0.1,
            'gacha_discount_rate' => 0.25,
            'display_badge_url' => 'https://example.com/badge_5.png',
            'sort_desc' => 5,
            'is_active' => true,
            ...$overrides,
        ]);
    }
}
