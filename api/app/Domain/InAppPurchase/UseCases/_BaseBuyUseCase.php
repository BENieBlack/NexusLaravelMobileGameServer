<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use NexusBilling\DataTransferObjects\Receipt;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;

/**
 * _BaseBuyUseCase
 *
 * アプリ内課金商品購入の共通フロー（Template Method Pattern）
 * レシート検証・価格検証などの共通処理を実装
 * 各商品タイプ（Diamond、Pack、Pass）固有の処理はサブクラスで実装
 */
abstract class _BaseBuyUseCase extends _BaseUseCase
{
    public function __construct(
        protected readonly InAppPurchaseValidationService $validationService,
        protected readonly BillingFacade $billingFacade,
    ) {}

    /**
     * 購入処理を実行（Template Method）
     *
     * 共通フロー:
     * 1. Receipt作成
     * 2. レシート検証（外部API、トランザクション外）
     * 3. プロダクトID一致確認
     * 4. 価格検証
     * 5. トランザクション内で購入処理実行（サブクラス実装）
     *
     * @param  int  $sysPlayerId  プレイヤーID（Controllerで認証済み）
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター（Controllerで検証済み）
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay, PayPal, Stripe）
     * @param  string  $receipt  レシート文字列
     * @param  string|null  $transactionId  トランザクションID
     * @param  string  $productId  プロダクトID
     *
     * @throws GameException
     */
    public function exec(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        string $receipt,
        ?string $transactionId,
        string $productId
    ): BuyResponse {
        // 1. レシートデータを作成
        $receiptData = $this->createReceiptData(
            $sysPlayerId,
            $billingPlatform,
            $receipt,
            $transactionId,
            $productId
        );

        // 2. 一意なリクエストIDを生成（重複防止用）
        $uniqueRequestId = $this->generateUniqueRequestId(
            $sysPlayerId,
            $mstInAppPurchase,
            $receiptData
        );

        // 3. レシート検証を実行（トランザクション開始前に外部API呼び出し）
        $verificationResult = $this->verifyReceipt(
            $billingPlatform,
            $receiptData,
            $uniqueRequestId
        );

        // 4. プロダクトIDが一致するか確認
        $this->validateProductId($verificationResult, $productId);

        // 5. 価格検証
        $this->validatePrice(
            $verificationResult,
            $mstInAppPurchase,
            $billingPlatform
        );

        // 6. トランザクション内で購入処理実行（サブクラス実装）
        return $this->executePurchase(
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $verificationResult
        );
    }

    /**
     * Receiptを作成
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  string  $receipt  レシート文字列
     * @param  string|null  $transactionId  トランザクションID
     * @param  string  $productId  プロダクトID
     */
    protected function createReceiptData(
        int $sysPlayerId,
        string $billingPlatform,
        string $receipt,
        ?string $transactionId,
        string $productId
    ): Receipt {
        return new Receipt(
            playerId: $sysPlayerId,
            billingPlatform: $billingPlatform,
            receipt: $billingPlatform === 'AppStore' ? $receipt : null,
            purchaseToken: $billingPlatform === 'GooglePlay' ? $receipt : null,
            productId: $productId,
            transactionId: $transactionId
        );
    }

    /**
     * 一意なリクエストIDを生成
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  Receipt  $receiptDto  レシートデータ
     */
    protected function generateUniqueRequestId(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        Receipt $receiptDto
    ): string {
        return $sysPlayerId.'_'.$mstInAppPurchase->getId().'_'.($receiptDto->getTransactionId() ?? time());
    }

    /**
     * レシートを検証
     *
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  Receipt  $receiptDto  レシートデータ
     * @param  string  $uniqueRequestId  一意なリクエストID
     */
    protected function verifyReceipt(
        string $billingPlatform,
        Receipt $receiptDto,
        string $uniqueRequestId
    ): Verification {
        return $this->billingFacade->processPurchase(
            billingPlatform: $billingPlatform,
            receiptDto: $receiptDto,
            uniqueRequestId: $uniqueRequestId
        );
    }

    /**
     * プロダクトIDを検証
     *
     * @param  Verification  $verificationDto  検証結果
     * @param  string  $productId  プロダクトID
     *
     * @throws GameException
     */
    protected function validateProductId(
        Verification $verificationDto,
        string $productId
    ): void {
        if ($verificationDto->getProductId() !== $productId) {
            throw new GameException(
                GameErrorCode::PRODUCT_ID_MISMATCH,
                'Product ID mismatch between request and receipt'
            );
        }
    }

    /**
     * 価格を検証
     *
     * @param  Verification  $verificationDto  検証結果
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム
     *
     * @throws GameException
     */
    protected function validatePrice(
        Verification $verificationDto,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): void {
        $this->validationService->validatePurchasePrice(
            $verificationDto,
            $mstInAppPurchase,
            $billingPlatform
        );
    }

    /**
     * 購入処理を実行（サブクラスで実装）
     *
     * トランザクション内で実行される商品タイプ固有の処理
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $platform  プラットフォーム
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  Verification  $verificationDto  レシート検証結果
     */
    abstract protected function executePurchase(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Verification $verificationDto
    ): BuyResponse;
}
