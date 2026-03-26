<?php

namespace App\Domain\Billing\Facades;

use App\Domain\Billing\Constants\BillingConst;
use App\Domain\Billing\DTOs\ReceiptData;
use App\Domain\Billing\DTOs\SubscriptionStatus;
use App\Domain\Billing\DTOs\VerificationResult;
use App\Domain\Billing\Exceptions\DuplicatePurchaseException;
use App\Domain\Billing\Services\BillingPlatformFactory;
use App\Domain\Billing\Services\IdempotencyService;
use App\Repositories\Log\LogInAppPurchaseRepository;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Billing ファサード
 * 
 * 外部から呼ばれる統一API
 * プラットフォーム固有の実装を抽象化し、統一的なインターフェースを提供
 */
class BillingFacade
{
    public function __construct(
        private readonly BillingPlatformFactory $platformFactory,
        private readonly IdempotencyService $idempotencyService,
        private readonly LogInAppPurchaseRepository $logRepository,
    ) {}

    /**
     * 購入処理（レシート検証）
     * 
     * 外部から呼ばれるメインAPI
     * どのプラットフォームでも同じように使える統一インターフェース
     * 
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @param ReceiptData $receiptData レシート情報
     * @param string $uniqueRequestId 一意なリクエストID（重複防止用）
     * @return VerificationResult 検証結果
     * @throws DuplicatePurchaseException 重複購入の場合
     * @throws Exception その他のエラー
     */
    public function processPurchase(
        string $billingPlatform,
        ReceiptData $receiptData,
        string $uniqueRequestId
    ): VerificationResult {
        // 1. 冪等性チェック（重複購入防止）
        if ($this->idempotencyService->isDuplicate($uniqueRequestId)) {
            Log::warning('Duplicate purchase request detected', [
                'unique_request_id' => $uniqueRequestId,
                'player_id' => $receiptData->playerId,
                'billing_platform' => $billingPlatform,
            ]);

            throw new DuplicatePurchaseException(
                "Purchase already processed: {$uniqueRequestId}"
            );
        }

        // 2. プラットフォーム選択（Factory）
        $platform = $this->platformFactory->create($billingPlatform);

        try {
            // 3. レシート検証（各プラットフォームの実装）
            $result = $platform->verifyReceipt($receiptData);

            // 4. 検証成功ログ記録
            $this->logRepository->createPurchaseLog(
                uniqueRequestId: $uniqueRequestId,
                sysPlayerId: $receiptData->playerId,
                platform: $billingPlatform, // TODO: デバイスプラットフォーム（Apple/Google）を別途取得
                billingPlatform: $billingPlatform,
                receiptId: $result->transactionId,
                receipt: $result->rawResponse,
                status: BillingConst::RECEIPT_STATUS_VERIFIED,
                mstInAppPurchaseId: '', // TODO: 商品IDを別途渡す必要がある
                currencyCode: 'USD', // TODO: 実際の通貨コードを取得
                payAmount: 0.0, // TODO: 実際の金額を取得
                payString: '', // TODO: 決済文字列を取得
            );

            // 5. 冪等性キー登録
            $this->idempotencyService->register($uniqueRequestId, $result);

            Log::info('Receipt verification successful', [
                'unique_request_id' => $uniqueRequestId,
                'transaction_id' => $result->transactionId,
                'product_id' => $result->productId,
                'billing_platform' => $billingPlatform,
            ]);

            return $result;

        } catch (Exception $e) {
            // 6. エラーログ記録
            $this->logRepository->createPurchaseLog(
                uniqueRequestId: $uniqueRequestId,
                sysPlayerId: $receiptData->playerId,
                platform: $billingPlatform,
                billingPlatform: $billingPlatform,
                receiptId: $receiptData->transactionId ?? '',
                receipt: ['error' => $e->getMessage(), 'receipt_data' => $receiptData->toArray()],
                status: BillingConst::RECEIPT_STATUS_FAILED,
                mstInAppPurchaseId: '',
                currencyCode: '',
                payAmount: 0.0,
                payString: '',
            );

            Log::error('Receipt verification failed', [
                'unique_request_id' => $uniqueRequestId,
                'player_id' => $receiptData->playerId,
                'billing_platform' => $billingPlatform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * サブスクリプション状態確認
     * 
     * @param string $billingPlatform 決済プラットフォーム
     * @param string $subscriptionId サブスクリプションID
     * @return SubscriptionStatus サブスクリプション状態
     */
    public function checkSubscription(
        string $billingPlatform,
        string $subscriptionId
    ): SubscriptionStatus {
        $platform = $this->platformFactory->create($billingPlatform);
        
        return $platform->getSubscriptionStatus($subscriptionId);
    }

    /**
     * 返金確認
     * 
     * @param string $billingPlatform 決済プラットフォーム
     * @param string $transactionId トランザクションID
     * @return bool 返金されているか
     */
    public function isRefunded(
        string $billingPlatform,
        string $transactionId
    ): bool {
        $platform = $this->platformFactory->create($billingPlatform);
        
        return $platform->isRefunded($transactionId);
    }

    /**
     * プラットフォームがサポートされているかチェック
     * 
     * @param string $billingPlatform
     * @return bool
     */
    public function isSupportedPlatform(string $billingPlatform): bool
    {
        return $this->platformFactory->isSupported($billingPlatform);
    }
}
