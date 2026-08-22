# Laravel Mobile Billing

モバイルアプリの課金処理を簡単に実装できるLaravelパッケージです。App StoreとGoogle Playのレシート検証、サブスクリプション管理、重複購入防止機能を提供します。

## 機能

- ✅ **App Store レシート検証** - Apple App Store の購入レシート検証
- ✅ **Google Play レシート検証** - Google Play の購入トークン検証  
- ✅ **重複購入防止** - 冪等性管理による重複購入の自動防止
- ✅ **サブスクリプション管理** - サブスクリプション商品の状態確認
- ✅ **統一API** - プラットフォームに依存しない統一的なインターフェース
- ✅ **返金確認** - 購入の返金状態チェック

## 要件

- PHP 8.1以上
- Laravel 10.x / 11.x / 12.x

## インストール

### 1. Composerでインストール

```bash
composer require laravel-mobile-rpg/mobile-billing
```

### 2. 設定ファイルの公開

```bash
php artisan vendor:publish --tag=mobile-billing-config
```

### 3. 環境変数の設定

`.env`ファイルに以下の設定を追加します。

```env
# App Store（レガシーのレシート検証）
APP_STORE_SHARED_SECRET=your_app_store_shared_secret

# App Store Server API（返金確認・サブスク照会）
APP_STORE_ENVIRONMENT=sandbox          # production / sandbox
APP_STORE_JWT_KEY_ID=ABCD123456        # 鍵ID（JWTヘッダーのkid）
APP_STORE_JWT_ISSUER_ID=...            # Issuer ID（iss）
APP_STORE_JWT_BUNDLE_ID=com.example.yourapp
APP_STORE_JWT_PRIVATE_KEY=/path/to/AuthKey_ABCD123456.p8   # パス or PEM文字列
APP_STORE_JWT_TTL=1800                 # 秒（Appleの上限3600）
APP_STORE_JWT_ROOT_CERTIFICATE=        # 任意。設定すると証明書チェーンも検証する

# Google Play
GOOGLE_PLAY_PACKAGE_NAME=com.example.yourapp
GOOGLE_PLAY_SERVICE_ACCOUNT=/path/to/service-account.json   # パス or JSON文字列
```

## 基本的な使い方

### App Store レシート検証

```php
use LaravelMobileBilling\Facades\BillingFacade;
use LaravelMobileBilling\DataTransferObjects\ReceiptData;
use LaravelMobileBilling\Constants\BillingConst;

// レシートデータの作成
$receiptData = new ReceiptData(
    playerId: $playerId,
    billingPlatform: BillingConst::PLATFORM_APP_STORE,
    receipt: $base64EncodedReceipt,
);

// 一意なリクエストIDを生成（重複防止用）
$uniqueRequestId = $playerId . '_' . time() . '_' . $transactionId;

// 購入処理（冪等性チェック付き）
$result = app(BillingFacade::class)->processPurchase(
    billingPlatform: BillingConst::PLATFORM_APP_STORE,
    receiptData: $receiptData,
    uniqueRequestId: $uniqueRequestId
);

// 検証結果の利用
echo "Transaction ID: " . $result->transactionId;
echo "Product ID: " . $result->productId;
echo "Purchase Date: " . $result->purchaseDate->toDateTimeString();
echo "Quantity: " . $result->quantity;
```

### Google Play レシート検証

```php
use LaravelMobileBilling\Facades\BillingFacade;
use LaravelMobileBilling\DataTransferObjects\ReceiptData;
use LaravelMobileBilling\Constants\BillingConst;

// レシートデータの作成
$receiptData = new ReceiptData(
    playerId: $playerId,
    billingPlatform: BillingConst::PLATFORM_GOOGLE_PLAY,
    purchaseToken: $purchaseToken,
    productId: $productId,
);

// 購入処理
$result = app(BillingFacade::class)->processPurchase(
    billingPlatform: BillingConst::PLATFORM_GOOGLE_PLAY,
    receiptData: $receiptData,
    uniqueRequestId: $uniqueRequestId
);
```

### 冪等性チェックなしの検証

```php
// 冪等性管理が不要な場合
$result = app(BillingFacade::class)->verifyReceipt(
    billingPlatform: BillingConst::PLATFORM_APP_STORE,
    receiptData: $receiptData
);
```

### サブスクリプション状態確認

> **状態: 照会のみ実装済み。購入フローは未実装。**
> 詳細は「サブスクリプション対応の残作業」を参照。

```php
$subscription = app(BillingFacade::class)->checkSubscription(
    billingPlatform: BillingConst::PLATFORM_APP_STORE,
    subscriptionId: $transactionId,   // App Storeは originalTransactionId
);

// Google Playは購入トークンが必須
$subscription = app(BillingFacade::class)->checkSubscription(
    billingPlatform: BillingConst::PLATFORM_GOOGLE_PLAY,
    subscriptionId: $subscriptionId,
    purchaseToken: $purchaseToken,
);

if ($subscription->isActive()) {
    echo '有効期限: '.$subscription->getExpiresAt();   // 'Y-m-d H:i:s' の文字列
    echo '自動更新: '.($subscription->isAutoRenew() ? 'あり' : 'なし');
}
```

### 返金確認

```php
$isRefunded = app(BillingFacade::class)->isRefunded(
    billingPlatform: BillingConst::PLATFORM_APP_STORE,
    transactionId: $transactionId
);

if ($isRefunded) {
    // 返金処理
}
```

## サブスクリプション対応の残作業

現状はプラットフォームへの**状態照会だけ**が実装されている。
サブスク商品を販売するには以下が必要になる。

| 項目 | 現状 |
|---|---|
| 商品マスタの種別 | `mst_in_app_purchase.type` は `Diamond` / `Pack` / `Pass` のみ。`Subscription` の追加にはマイグレーションが必要 |
| 購入フロー | `BuyDiamondUseCase` / `BuyPackUseCase` / `BuyPassUseCase` のみ。サブスク用のUseCaseが無い |
| 更新通知の受け口 | App Store Server Notifications V2 / Google RTDN(Pub/Sub) のエンドポイントが無い。更新・解約・課金失敗はこれで受けるのが標準で、照会のポーリングだけでは足りない |
| 期限・状態の保持 | `trx_in_app_purchase` は購入回数のみで、有効期限や自動更新状態の列が無い |
| 権利の管理 | パス商品の `trx_in_app_purchase_effect`（`expires_at` / `is_active`）が期間限定の権利をすでに扱っており、これを拡張するのが素直 |

`checkSubscription()` は上記が揃うまで呼び出し元が無い。
実装済みの認証基盤（App Store Server APIのJWT認証・JWS検証、Google PlayのOAuth）は
返金確認と共通のため、サブスク対応時にもそのまま使える。

## 例外処理

```php
use LaravelMobileBilling\Exceptions\DuplicatePurchaseException;
use LaravelMobileBilling\Exceptions\InvalidReceiptException;
use LaravelMobileBilling\Exceptions\PlatformApiException;

try {
    $result = app(BillingFacade::class)->processPurchase(
        billingPlatform: $platform,
        receiptData: $receiptData,
        uniqueRequestId: $uniqueRequestId
    );
    
} catch (DuplicatePurchaseException $e) {
    // 重複購入
    Log::warning('Duplicate purchase: ' . $e->getMessage());
    
} catch (InvalidReceiptException $e) {
    // 無効なレシート
    Log::error('Invalid receipt: ' . $e->getMessage());
    
} catch (PlatformApiException $e) {
    // プラットフォームAPIエラー
    Log::error('Platform API error: ' . $e->getMessage());
}
```

## DTOリファレンス

### ReceiptData

レシート情報を保持するDTO

```php
new ReceiptData(
    playerId: int,              // プレイヤーID
    billingPlatform: string,    // プラットフォーム名
    receipt: ?string,           // App Store用: base64エンコードされたレシート
    purchaseToken: ?string,     // Google Play用: 購入トークン
    productId: ?string,         // Google Play用: 商品ID
    transactionId: ?string,     // トランザクションID（オプション）
)
```

### VerificationResult

レシート検証結果を保持するDTO

```php
class VerificationResult {
    public bool $isValid;                      // 検証が成功したか
    public string $transactionId;              // トランザクションID
    public string $productId;                  // 商品ID
    public CarbonImmutable $purchaseDate;      // 購入日時
    public int $quantity;                      // 購入数量
    public string $originalTransactionId;      // 元のトランザクションID
    public array $rawResponse;                 // プラットフォームAPIの生レスポンス
}
```

### SubscriptionStatus

サブスクリプション状態を保持するDTO

```php
class SubscriptionStatus {
    public bool $isActive;                     // サブスクリプションが有効か
    public CarbonImmutable $expiresAt;         // 有効期限
    public bool $autoRenew;                    // 自動更新が有効か
    public ?string $state;                     // 状態（active, expired, cancelled等）
    public ?CarbonImmutable $cancelledAt;      // キャンセル日時
}
```

## 設定

`config/mobile-billing.php`で以下の設定が可能です。

```php
return [
    'app_store' => [
        'shared_secret' => env('APP_STORE_SHARED_SECRET'),
        'use_sandbox' => env('APP_STORE_USE_SANDBOX', false),
    ],
    
    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME'),
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
    ],
    
    'idempotency' => [
        'cache_ttl' => 86400,  // 24時間
        'cache_prefix' => 'billing:idempotency:',
    ],
];
```

## ライセンス

MIT License
