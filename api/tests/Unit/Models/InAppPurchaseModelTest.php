<?php

namespace Tests\Unit\Models;

use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;
use App\Models\Mst\MstInAppPurchaseContent;
use App\Models\Mst\MstInAppPurchaseEffect;
use App\Models\Trx\TrxInAppPurchase;
use App\Models\Trx\TrxInAppPurchaseEffect;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 課金まわりのモデルのテスト
 *
 * パックの中身の数量、購入回数の積み上がり、パス効果の有効判定。
 * どれも取り違えると付与量や購入可否が狂う。
 */
class InAppPurchaseModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    // ========================================
    // パックの中身
    // ========================================

    #[Test]
    public function 中身の値を読み出せる(): void
    {
        $content = $this->makeContent(['content_option' => ['grade' => 1]]);

        $this->assertSame(1, $content->getMstInAppPurchaseId());
        $this->assertSame(InAppPurchaseConst::CONTENT_TYPE_ITEM, $content->getContentType());
        $this->assertSame('item_potion', $content->getContentMstId());
        $this->assertSame(['grade' => 1], $content->getContentOption());
        $this->assertSame(10, $content->getContentQuantity());
        $this->assertSame(3, $content->getAmount());
        $this->assertSame(5, $content->getSortDesc());
    }

    #[Test]
    public function 中身の配布量は基本個数と配布回数の積になる(): void
    {
        // 片方だけ見ると数が合わない
        $this->assertSame(30, $this->makeContent()->getTotalQuantity());
        $this->assertSame(1, $this->makeContent(['content_quantity' => 1, 'amount' => 1])->getTotalQuantity());
    }

    #[Test]
    public function 中身のオプションは未設定でもよい(): void
    {
        $this->assertNull($this->makeContent()->getContentOption());
    }

    #[Test]
    public function 効果の値を読み出せる(): void
    {
        $effect = new MstInAppPurchaseEffect([
            'mst_in_app_purchase_id' => 1,
            'effect_type' => InAppPurchaseConst::EFFECT_TYPE_EXP_BOOST,
            'value' => 1.5,
        ]);

        $this->assertSame(InAppPurchaseConst::EFFECT_TYPE_EXP_BOOST, $effect->getEffectType());
        $this->assertSame(1.5, $effect->getValue());
    }

    // ========================================
    // 購入履歴
    // ========================================

    #[Test]
    public function 購入回数を出し入れできる(): void
    {
        // purchase_count は制限のリセットで0に戻るが、
        // total_purchase_count は戻らない
        $history = new TrxInAppPurchase;
        $history->setSysPlayerId(1);
        $history->setBillingPlatform('google_play');
        $history->setMstInAppPurchaseId(10);
        $history->setTotalPurchaseCount(7);
        $history->setPurchaseCount(2);
        $history->setPurchaseCountResetAt('2026-03-15 00:00:00');

        $this->assertSame(7, $history->getTotalPurchaseCount());
        $this->assertSame(2, $history->getPurchaseCount());
        $this->assertSame('2026-03-15 00:00:00', $history->getPurchaseCountResetAt());
    }

    #[Test]
    public function リセット日時は未設定にできる(): void
    {
        // リセットなしの商品では日時を持たない
        $history = new TrxInAppPurchase;
        $history->setPurchaseCountResetAt(null);

        $this->assertNull($history->getPurchaseCountResetAt());
    }

    // ========================================
    // パス効果
    // ========================================

    #[Test]
    public function 期限内で有効なら効果が効く(): void
    {
        $effect = $this->makeTrxEffect(expiresAt: '2026-03-16 12:00:00');

        $this->assertTrue($effect->isEffective());
    }

    #[Test]
    public function 期限切れの効果は効かない(): void
    {
        $effect = $this->makeTrxEffect(expiresAt: '2026-03-15 11:59:59');

        $this->assertFalse($effect->isEffective());
    }

    #[Test]
    public function 無効にした効果は期限内でも効かない(): void
    {
        // 返金時などに手で落とす経路がある
        $effect = $this->makeTrxEffect(expiresAt: '2026-03-16 12:00:00');
        $effect->setIsActive(false);

        $this->assertFalse($effect->getIsActive());
        $this->assertFalse($effect->isEffective());
    }

    #[Test]
    public function 効果の値を出し入れできる(): void
    {
        $effect = new TrxInAppPurchaseEffect;
        $effect->setSysPlayerId(1);
        $effect->setMstInAppPurchaseId(10);
        $effect->setEffectType(InAppPurchaseConst::EFFECT_TYPE_GOLD_BOOST);
        $effect->setValue(2.0);
        $effect->setExpiresAt(ClockUtility::now()->addDays(30));
        $effect->setIsActive(true);
        $effect->setIsDelete(false);

        $this->assertSame(InAppPurchaseConst::EFFECT_TYPE_GOLD_BOOST, $effect->getAttribute('effect_type'));
        $this->assertSame('2026-04-14 12:00:00', $effect->getExpiresAt());
        $this->assertTrue($effect->isEffective());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeContent(array $attributes = []): MstInAppPurchaseContent
    {
        return new MstInAppPurchaseContent(array_merge([
            'mst_in_app_purchase_id' => 1,
            'content_type' => InAppPurchaseConst::CONTENT_TYPE_ITEM,
            'content_mst_id' => 'item_potion',
            'content_quantity' => 10,
            'amount' => 3,
            'sort_desc' => 5,
        ], $attributes));
    }

    private function makeTrxEffect(string $expiresAt): TrxInAppPurchaseEffect
    {
        $effect = new TrxInAppPurchaseEffect;
        $effect->setSysPlayerId(1);
        $effect->setMstInAppPurchaseId(10);
        $effect->setEffectType(InAppPurchaseConst::EFFECT_TYPE_EXP_BOOST);
        $effect->setValue(1.5);
        $effect->setExpiresAt(ClockUtility::parse($expiresAt));
        $effect->setIsActive(true);

        return $effect;
    }
}
