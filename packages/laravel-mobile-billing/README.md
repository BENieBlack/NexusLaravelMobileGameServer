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
# App Store
APP_STORE_SHARED_SECRET=your_app_store_shared_secret

# Google Play
GOOGLE_PLAY_PACKAGE_NAME=com.example.yourapp
GOOGLE_PLAY_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
```

## 基本的な使い方

### App Store レシート検証

```php
use LaravelMobileBilling\Facades\BillingFacade;
use LaravelMobileBilling\DTOs\ReceiptData;
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
use LaravelMobileBilling\DTOs\ReceiptData;
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

```php
$status = app(BillingFacade::class)->checkSubscription(
    billingPlatform: BillingConst::PLATFORM_APP_STORE,
    subscriptionId: $subscriptionId
);

if ($status->isActive) {
    echo "Subscription is active until: " . $status->expiresAt->toDateTimeString();
    echo "Auto-renew: " . ($status->autoRenew ? 'Yes' : 'No');
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
