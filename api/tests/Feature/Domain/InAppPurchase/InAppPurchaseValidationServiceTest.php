<?php

namespace Tests\Feature\Domain\InAppPurchase;

use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstInAppPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NexusBilling\DataTransferObjects\Verification;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * InAppPurchaseValidationService の価格検証テスト
 *
 * レシートの価格とマスターの期待価格が食い違う購入を弾く。
 * 金銭に直結するため、通す条件と弾く条件の両方を押さえる。
 */
class InAppPurchaseValidationServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const PRODUCT_ID = 990001;

    private const APP_STORE_PRODUCT_ID = 9001;

    private const GOOGLE_PLAY_PRODUCT_ID = 9002;

    private InAppPurchaseValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        $this->cleanUpMaster();
        $this->service = app(InAppPurchaseValidationService::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUpMaster();

        parent::tearDown();
    }

    #[Test]
    public function 価格が一致すれば通す(): void
    {
        $mstInAppPurchase = $this->makeProduct(googlePlayPriceMicros: 480_000_000);

        $this->service->validatePurchasePrice(
            $this->makeVerification(priceMicros: 480_000_000, currency: 'JPY'),
            $mstInAppPurchase,
            'GooglePlay',
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 価格が食い違えば弾く(): void
    {
        $mstInAppPurchase = $this->makeProduct(googlePlayPriceMicros: 480_000_000);

        $this->expectGameException(GameErrorCode::PRICE_MISMATCH);

        // 実際に支払われた額がマスターより安い（改ざんの疑い）
        $this->service->validatePurchasePrice(
            $this->makeVerification(priceMicros: 120_000_000, currency: 'JPY'),
            $mstInAppPurchase,
            'GooglePlay',
        );
    }

    #[Test]
    public function 通貨が食い違えば弾く(): void
    {
        $mstInAppPurchase = $this->makeProduct(googlePlayPriceMicros: 480_000_000, currency: 'JPY');

        $this->expectGameException(GameErrorCode::PRICE_MISMATCH);

        $this->service->validatePurchasePrice(
            $this->makeVerification(priceMicros: 480_000_000, currency: 'USD'),
            $mstInAppPurchase,
            'GooglePlay',
        );
    }

    #[Test]
    public function レシートに価格が無ければ検証しない(): void
    {
        // App Store の verifyReceipt は価格を返さない
        $mstInAppPurchase = $this->makeProduct(appStorePriceMicros: 480_000_000);

        $this->service->validatePurchasePrice(
            $this->makeVerification(priceMicros: null, currency: null),
            $mstInAppPurchase,
            'AppStore',
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function マスターに価格が無ければ検証しない(): void
    {
        $mstInAppPurchase = $this->makeProduct(googlePlayPriceMicros: null);

        $this->service->validatePurchasePrice(
            $this->makeVerification(priceMicros: 480_000_000, currency: 'JPY'),
            $mstInAppPurchase,
            'GooglePlay',
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 未知のプラットフォームは検証しない(): void
    {
        $mstInAppPurchase = $this->makeProduct(googlePlayPriceMicros: 480_000_000);

        // 対応するプラットフォーム商品が引けないため素通しする
        $this->service->validatePurchasePrice(
            $this->makeVerification(priceMicros: 120_000_000, currency: 'JPY'),
            $mstInAppPurchase,
            'AmazonAppstore',
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 購入価格はレシートの値を優先する(): void
    {
        $mstInAppPurchase = $this->makeProduct(googlePlayPriceMicros: 480_000_000);

        $price = $this->service->resolvePurchasePrice(
            $this->makeVerification(priceMicros: 610_000_000, currency: 'JPY'),
            $mstInAppPurchase,
            'GooglePlay',
        );

        $this->assertSame(610.0, $price);
    }

    #[Test]
    public function レシートに価格が無ければマスターの値を使う(): void
    {
        // App Store は価格を返さないため、マスターの設定値で返金額を計算する
        $mstInAppPurchase = $this->makeProduct(appStorePriceMicros: 480_000_000);

        $price = $this->service->resolvePurchasePrice(
            $this->makeVerification(priceMicros: null, currency: null),
            $mstInAppPurchase,
            'AppStore',
        );

        $this->assertSame(480.0, $price);
    }

    #[Test]
    public function 価格がどこにも無ければ0を返す(): void
    {
        $mstInAppPurchase = $this->makeProduct(appStorePriceMicros: null);

        $price = $this->service->resolvePurchasePrice(
            $this->makeVerification(priceMicros: null, currency: null),
            $mstInAppPurchase,
            'AppStore',
        );

        $this->assertSame(0.0, $price, '金額不明のまま固定値を入れない');
    }

    #[Test]
    public function 通貨もレシートを優先しマスターへ落ちる(): void
    {
        $mstInAppPurchase = $this->makeProduct(appStorePriceMicros: 480_000_000, currency: 'JPY');

        $this->assertSame(
            'USD',
            $this->service->resolvePurchaseCurrency(
                $this->makeVerification(priceMicros: 480_000_000, currency: 'USD'),
                $mstInAppPurchase,
                'AppStore',
            ),
        );

        $this->assertSame(
            'JPY',
            $this->service->resolvePurchaseCurrency(
                $this->makeVerification(priceMicros: null, currency: null),
                $mstInAppPurchase,
                'AppStore',
            ),
        );
    }

    #[Test]
    public function マスターの価格を取り出せる(): void
    {
        $mstInAppPurchase = $this->makeProduct(appStorePriceMicros: 480_000_000, currency: 'JPY');

        $this->assertSame(
            ['amount' => 480.0, 'currency' => 'JPY'],
            $this->service->findMasterPrice($mstInAppPurchase, 'AppStore'),
        );
    }

    #[Test]
    public function マスターに価格が無ければ0と通貨なしを返す(): void
    {
        $mstInAppPurchase = $this->makeProduct(appStorePriceMicros: null);

        $this->assertSame(
            ['amount' => 0.0, 'currency' => null],
            $this->service->findMasterPrice($mstInAppPurchase, 'AppStore'),
        );
    }

    private function makeProduct(
        ?int $appStorePriceMicros = null,
        ?int $googlePlayPriceMicros = null,
        ?string $currency = 'JPY',
    ): MstInAppPurchase {
        $this->makePlatformProduct(self::APP_STORE_PRODUCT_ID, 'AppStore', $appStorePriceMicros, $currency);
        $this->makePlatformProduct(self::GOOGLE_PLAY_PRODUCT_ID, 'GooglePlay', $googlePlayPriceMicros, $currency);

        DB::connection('mst')->table('mst_in_app_purchase')->insert([
            'id' => self::PRODUCT_ID,
            'type' => 'Diamond',
            'paid_diamond_amount' => 100,
            'vip_point' => 0,
            'purchase_limit' => null,
            'purchase_limit_reset' => 'None',
            'app_store_product_id' => self::APP_STORE_PRODUCT_ID,
            'google_play_product_id' => self::GOOGLE_PLAY_PRODUCT_ID,
            'is_active' => true,
        ]);

        $this->refreshMstCache();

        return MstInAppPurchase::query()->where('id', self::PRODUCT_ID)->firstOrFail();
    }

    private function makePlatformProduct(int $id, string $platform, ?int $priceMicros, ?string $currency): void
    {
        DB::connection('mst')->table('mst_billing_platform_product')->insert([
            'id' => $id,
            'platform_product_id' => 'store.'.self::PRODUCT_ID,
            'billing_platform' => $platform,
            'product_type' => 'Consumable',
            'price_amount_micros' => $priceMicros,
            'price_currency_code' => $priceMicros === null ? null : $currency,
            'is_active' => true,
        ]);
    }

    private function makeVerification(?int $priceMicros, ?string $currency): Verification
    {
        return new Verification(
            isValid: true,
            transactionId: 'txn-validation-001',
            productId: 'store.'.self::PRODUCT_ID,
            purchaseDate: '2026-03-15 12:00:00',
            quantity: 1,
            originalTransactionId: 'txn-validation-001',
            rawResponse: [],
            priceAmountMicros: $priceMicros,
            priceCurrencyCode: $currency,
        );
    }

    private function expectGameException(int $errorCode): void
    {
        $this->expectException(GameException::class);
        $this->expectExceptionCode($errorCode);
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_in_app_purchase')->where('id', self::PRODUCT_ID)->delete();
        DB::connection('mst')->table('mst_billing_platform_product')
            ->whereIn('id', [self::APP_STORE_PRODUCT_ID, self::GOOGLE_PLAY_PRODUCT_ID])->delete();
        $this->refreshMstCache();
    }
}
