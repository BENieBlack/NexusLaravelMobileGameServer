<?php

namespace Tests\Unit\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Models\Mst\MstBillingPlatformProduct;
use App\Models\Mst\MstInAppPurchase;
use NexusBilling\DataTransferObjects\Verification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 購入価格の解決のテスト
 *
 * trx_diamond_balance.unit_price に入れる返金計算用の金額を決める処理。
 * 以前はUseCaseで 1.0 固定だった。
 */
class ResolvePurchasePriceTest extends TestCase
{
    private InAppPurchaseValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InAppPurchaseValidationService;
    }

    #[Test]
    public function レシート検証結果の価格を使う(): void
    {
        $price = $this->service->resolvePurchasePrice(
            $this->verification(priceAmountMicros: 4_900_000),
            new MstInAppPurchase,
            'GooglePlay'
        );

        $this->assertSame(4.9, $price);
    }

    #[Test]
    public function 検証結果に価格が無ければマスターの設定値を使う(): void
    {
        $mstInAppPurchase = new MstInAppPurchase;
        $mstInAppPurchase->setRelation('appStoreProduct', $this->platformProduct(1_200_000));

        $price = $this->service->resolvePurchasePrice(
            $this->verification(priceAmountMicros: null),
            $mstInAppPurchase,
            'app_store'
        );

        $this->assertSame(1.2, $price);
    }

    #[Test]
    public function 価格がどこにも無ければゼロを返す(): void
    {
        $mstInAppPurchase = new MstInAppPurchase;
        $mstInAppPurchase->setRelation('appStoreProduct', null);

        $price = $this->service->resolvePurchasePrice(
            $this->verification(priceAmountMicros: null),
            $mstInAppPurchase,
            'app_store'
        );

        // 金額不明のまま固定値を入れると返金計算が狂うため0.0を返す
        $this->assertSame(0.0, $price);
    }

    private function verification(?int $priceAmountMicros): Verification
    {
        return new Verification(
            isValid: true,
            transactionId: 'GPA.0000-1111-2222-33333',
            productId: 'diamond_100',
            purchaseDate: '2026-08-20 10:00:00',
            quantity: 1,
            originalTransactionId: 'GPA.0000-1111-2222-33333',
            rawResponse: [],
            priceAmountMicros: $priceAmountMicros,
            priceCurrencyCode: $priceAmountMicros === null ? null : 'JPY',
        );
    }

    private function platformProduct(int $priceAmountMicros): MstBillingPlatformProduct
    {
        $product = new MstBillingPlatformProduct;
        $product->setAttribute('price_amount_micros', $priceAmountMicros);
        $product->setAttribute('price_currency_code', 'JPY');

        return $product;
    }
}
