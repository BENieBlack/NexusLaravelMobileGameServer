<?php

namespace NexusVip\ValueObjects;

/**
 * VIP特典 Value Object
 *
 * VIPレベルごとの特典内容を保持する不変オブジェクト。
 *
 * 特典の適用計算（割引・ボーナス加算）は特典そのものの振る舞いなので、
 * Service側ではなくこのオブジェクトが持つ。
 * Service側は「特典が有効かどうか」の設定判定だけを担当する。
 */
final class VipBenefit
{
    /**
     * @param  int  $maxStaminaBonus  スタミナ上限ボーナス
     * @param  int  $dailyDiamondBonus  デイリーダイヤモンドボーナス
     * @param  float  $shopDiscountRate  ショップ割引率 (0.0-1.0)
     * @param  float  $gachaDiscountRate  ガチャ割引率 (0.0-1.0)
     *
     * @throws \InvalidArgumentException 値が範囲外の場合
     */
    public function __construct(
        private readonly int $maxStaminaBonus,
        private readonly int $dailyDiamondBonus,
        private readonly float $shopDiscountRate,
        private readonly float $gachaDiscountRate,
    ) {
        if ($maxStaminaBonus < 0) {
            throw new \InvalidArgumentException("スタミナ上限ボーナスは0以上である必要があります: {$maxStaminaBonus}");
        }

        if ($dailyDiamondBonus < 0) {
            throw new \InvalidArgumentException("デイリーダイヤモンドボーナスは0以上である必要があります: {$dailyDiamondBonus}");
        }

        self::assertRate('ショップ割引率', $shopDiscountRate);
        self::assertRate('ガチャ割引率', $gachaDiscountRate);
    }

    /**
     * 特典なし（VIP0相当）のインスタンスを生成
     */
    public static function none(): self
    {
        return new self(0, 0, 0.0, 0.0);
    }

    /**
     * スタミナ上限ボーナス取得
     */
    public function getMaxStaminaBonus(): int
    {
        return $this->maxStaminaBonus;
    }

    /**
     * デイリーダイヤモンドボーナス取得
     */
    public function getDailyDiamondBonus(): int
    {
        return $this->dailyDiamondBonus;
    }

    /**
     * ショップ割引率取得
     */
    public function getShopDiscountRate(): float
    {
        return $this->shopDiscountRate;
    }

    /**
     * ガチャ割引率取得
     */
    public function getGachaDiscountRate(): float
    {
        return $this->gachaDiscountRate;
    }

    /**
     * スタミナ上限にボーナスを加算した値を返す
     */
    public function applyStaminaBonus(int $baseMaxStamina): int
    {
        return $baseMaxStamina + $this->maxStaminaBonus;
    }

    /**
     * ショップ価格に割引を適用した値を返す（最低価格は1）
     */
    public function applyShopDiscount(int $basePrice): int
    {
        return self::discount($basePrice, $this->shopDiscountRate);
    }

    /**
     * ガチャ価格に割引を適用した値を返す（最低価格は1）
     */
    public function applyGachaDiscount(int $basePrice): int
    {
        return self::discount($basePrice, $this->gachaDiscountRate);
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->maxStaminaBonus === $other->maxStaminaBonus
            && $this->dailyDiamondBonus === $other->dailyDiamondBonus
            && $this->shopDiscountRate === $other->shopDiscountRate
            && $this->gachaDiscountRate === $other->gachaDiscountRate;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'max_stamina_bonus' => $this->maxStaminaBonus,
            'daily_diamond_bonus' => $this->dailyDiamondBonus,
            'shop_discount_rate' => $this->shopDiscountRate,
            'gacha_discount_rate' => $this->gachaDiscountRate,
        ];
    }

    /**
     * 割引を適用（最低価格は1）
     */
    private static function discount(int $basePrice, float $rate): int
    {
        return max(1, (int) floor($basePrice - ($basePrice * $rate)));
    }

    /**
     * 割引率が0.0〜1.0の範囲内か検証
     */
    private static function assertRate(string $label, float $rate): void
    {
        if ($rate < 0.0 || $rate > 1.0) {
            throw new \InvalidArgumentException("{$label}は0.0〜1.0の範囲である必要があります: {$rate}");
        }
    }
}
