<?php

namespace Tests\Unit\Domain\InAppPurchase\Constants;

use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * InAppPurchaseConst のテスト
 *
 * 課金まわりの区分値。DBのenumと値が揃っていないと、
 * マスターに入れられても判定が通らない、あるいはその逆になる。
 *
 * とくにコンテンツタイプは購入時の付与先の振り分けに使うため、
 * 増減したときに気づける形にしておく。
 */
class InAppPurchaseConstTest extends TestCase
{
    #[Test]
    public function 商品タイプは3種(): void
    {
        $this->assertSame(
            [
                InAppPurchaseConst::TYPE_DIAMOND,
                InAppPurchaseConst::TYPE_PACK,
                InAppPurchaseConst::TYPE_PASS,
            ],
            InAppPurchaseConst::allTypes()
        );
    }

    #[Test]
    public function 購入制限リセットは4種(): void
    {
        $this->assertSame(
            [
                InAppPurchaseConst::PURCHASE_LIMIT_RESET_NONE,
                InAppPurchaseConst::PURCHASE_LIMIT_RESET_DAILY,
                InAppPurchaseConst::PURCHASE_LIMIT_RESET_WEEKLY,
                InAppPurchaseConst::PURCHASE_LIMIT_RESET_MONTHLY,
            ],
            InAppPurchaseConst::allPurchaseLimitResets()
        );
    }

    #[Test]
    public function コンテンツタイプは3種(): void
    {
        // 増やしたときは InAppPurchasePackService の振り分けも足す必要がある
        $this->assertSame(
            [
                InAppPurchaseConst::CONTENT_TYPE_ITEM,
                InAppPurchaseConst::CONTENT_TYPE_UNIT,
                InAppPurchaseConst::CONTENT_TYPE_FREE_DIAMOND,
            ],
            InAppPurchaseConst::allContentTypes()
        );
    }

    #[Test]
    public function 効果タイプは5種(): void
    {
        $this->assertSame(
            [
                InAppPurchaseConst::EFFECT_TYPE_IDLE_REWARD_MULTIPLIER,
                InAppPurchaseConst::EFFECT_TYPE_AD_SKIP,
                InAppPurchaseConst::EFFECT_TYPE_EXP_BOOST,
                InAppPurchaseConst::EFFECT_TYPE_GOLD_BOOST,
                InAppPurchaseConst::EFFECT_TYPE_DAILY_MISSION_BONUS,
            ],
            InAppPurchaseConst::allEffectTypes()
        );
    }

    #[Test]
    public function 定義された値だけを有効とみなす(): void
    {
        $checks = [
            'isValidType' => InAppPurchaseConst::allTypes(),
            'isValidPurchaseLimitReset' => InAppPurchaseConst::allPurchaseLimitResets(),
            'isValidContentType' => InAppPurchaseConst::allContentTypes(),
            'isValidEffectType' => InAppPurchaseConst::allEffectTypes(),
        ];

        foreach ($checks as $method => $values) {
            foreach ($values as $value) {
                $this->assertTrue(InAppPurchaseConst::{$method}($value), "{$method}({$value})");
            }

            $this->assertFalse(InAppPurchaseConst::{$method}('NoSuchValue'), $method);
        }
    }

    #[Test]
    public function 大文字小文字は区別する(): void
    {
        // DBのenumはパスカルケース。小文字で入れると保存できない
        $this->assertFalse(InAppPurchaseConst::isValidType('diamond'));
        $this->assertFalse(InAppPurchaseConst::isValidContentType('item'));
        $this->assertFalse(InAppPurchaseConst::isValidEffectType('expboost'));
    }

    #[Test]
    public function 区分をまたいだ値は有効にしない(): void
    {
        // 商品タイプとコンテンツタイプはどちらも似た語だが別物
        $this->assertFalse(InAppPurchaseConst::isValidType(InAppPurchaseConst::CONTENT_TYPE_ITEM));
        $this->assertFalse(InAppPurchaseConst::isValidContentType(InAppPurchaseConst::TYPE_PACK));
    }
}
