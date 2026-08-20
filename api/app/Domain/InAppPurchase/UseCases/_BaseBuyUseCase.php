<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Log\LogInAppPurchase;
use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Log\LogInAppPurchaseRepository;
use Illuminate\Support\Facades\Log;
use NexusBilling\DataTransferObjects\Receipt;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;
use NexusVip\Services\VipPointService;
use Throwable;

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
        protected readonly VipPointService $vipPointService,
        protected readonly LogInAppPurchaseRepository $logInAppPurchaseRepository,
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

        $verificationResult = null;

        try {
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

            // 6. 購入処理・VIPポイント付与・課金ログを1つのトランザクションで実行
            return $this->executeWithTransaction(function () use (
                $sysPlayerId,
                $mstInAppPurchase,
                $platform,
                $billingPlatform,
                $verificationResult,
                $uniqueRequestId
            ) {
                // 6-1. 商品タイプ固有の購入処理（サブクラス実装）
                $response = $this->executePurchase(
                    $sysPlayerId,
                    $mstInAppPurchase,
                    $platform,
                    $billingPlatform,
                    $verificationResult
                );

                // 6-2. VIPポイントを付与
                $this->grantVipPoint(
                    $sysPlayerId,
                    $mstInAppPurchase,
                    $billingPlatform,
                    $verificationResult,
                    $uniqueRequestId
                );

                // 6-3. 課金ログを記録
                $this->writePurchaseLog(
                    $sysPlayerId,
                    $mstInAppPurchase,
                    $platform,
                    $billingPlatform,
                    $verificationResult,
                    $uniqueRequestId
                );

                return $response;
            });
        } catch (Throwable $e) {
            // 失敗もCS調査で追えるように記録する。
            // トランザクションはロールバック済みのため、ログだけ別途書き込む。
            $this->writeFailedPurchaseLog(
                $sysPlayerId,
                $mstInAppPurchase,
                $platform,
                $billingPlatform,
                $receiptData,
                $verificationResult,
                $uniqueRequestId,
                $e
            );

            throw $e;
        }
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
     * @param  Receipt  $receipt  レシートデータ
     */
    protected function generateUniqueRequestId(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        Receipt $receipt
    ): string {
        return $sysPlayerId.'_'.$mstInAppPurchase->getId().'_'.($receipt->getTransactionId() ?? time());
    }

    /**
     * レシートを検証
     *
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  Receipt  $receipt  レシートデータ
     * @param  string  $uniqueRequestId  一意なリクエストID
     */
    protected function verifyReceipt(
        string $billingPlatform,
        Receipt $receipt,
        string $uniqueRequestId
    ): Verification {
        return $this->billingFacade->processPurchase(
            billingPlatform: $billingPlatform,
            receipt: $receipt,
            uniqueRequestId: $uniqueRequestId
        );
    }

    /**
     * プロダクトIDを検証
     *
     * @param  Verification  $verification  検証結果
     * @param  string  $productId  プロダクトID
     *
     * @throws GameException
     */
    protected function validateProductId(
        Verification $verification,
        string $productId
    ): void {
        if ($verification->getProductId() !== $productId) {
            throw new GameException(
                GameErrorCode::PRODUCT_ID_MISMATCH,
                'Product ID mismatch between request and receipt'
            );
        }
    }

    /**
     * 価格を検証
     *
     * @param  Verification  $verification  検証結果
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム
     *
     * @throws GameException
     */
    protected function validatePrice(
        Verification $verification,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): void {
        $this->validationService->validatePurchasePrice(
            $verification,
            $mstInAppPurchase,
            $billingPlatform
        );
    }

    /**
     * 購入価格を解決する（通貨単位）
     *
     * trx_diamond_balance.unit_price に入れる返金計算用の金額。
     *
     * @param  Verification  $verification  レシート検証結果
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム
     */
    protected function resolvePurchasePrice(
        Verification $verification,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): float {
        return $this->validationService->resolvePurchasePrice(
            $verification,
            $mstInAppPurchase,
            $billingPlatform
        );
    }

    /**
     * VIPポイントを付与する
     *
     * 商品マスターに vip_point が設定されている場合のみ加算する。
     * 累計課金額（sys_player.total_paid_amount）は円で保持しているため、
     * 換算レートを持っていないJPY以外の通貨では加算しない。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  Verification  $verification  レシート検証結果
     * @param  string  $uniqueRequestId  一意なリクエストID
     */
    protected function grantVipPoint(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform,
        Verification $verification,
        string $uniqueRequestId
    ): void {
        $vipPoint = $mstInAppPurchase->getVipPoint();

        if ($vipPoint <= 0) {
            return;
        }

        $metadata = [
            'unique_request_id' => $uniqueRequestId,
            'mst_in_app_purchase_id' => (string) $mstInAppPurchase->getId(),
            'billing_platform' => $billingPlatform,
            'transaction_id' => $verification->getTransactionId(),
        ];

        if ($this->resolveCurrency($verification, $mstInAppPurchase, $billingPlatform) === 'JPY') {
            $metadata['purchase_amount_jpy'] = $this->resolvePurchasePrice(
                $verification,
                $mstInAppPurchase,
                $billingPlatform
            );
        }

        $this->vipPointService->addPoints(
            sysPlayerId: $sysPlayerId,
            points: $vipPoint,
            reason: 'purchase',
            metadata: $metadata
        );
    }

    /**
     * 課金ログを記録する
     *
     * CS調査で購入の有無と金額を追えるようにするためのログ。
     * ビジネスデータと同じトランザクションで書き込む。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  Verification  $verification  レシート検証結果
     * @param  string  $uniqueRequestId  一意なリクエストID
     */
    protected function writePurchaseLog(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Verification $verification,
        string $uniqueRequestId
    ): void {
        $price = $this->resolvePurchasePrice($verification, $mstInAppPurchase, $billingPlatform);
        $currency = $this->resolveCurrency($verification, $mstInAppPurchase, $billingPlatform);

        $this->logInAppPurchaseRepository->insertPurchaseLog(
            uniqueRequestId: $uniqueRequestId,
            sysPlayerId: $sysPlayerId,
            // log_in_app_purchase.platform は enum('apple','google')
            platform: strtolower($platform),
            billingPlatform: $billingPlatform,
            receiptId: (string) $verification->getTransactionId(),
            receipt: $verification->getRawResponse(),
            status: LogInAppPurchase::STATUS_PURCHASED,
            mstInAppPurchaseId: (string) $mstInAppPurchase->getId(),
            currencyCode: $currency ?? '',
            payAmount: $price,
            payString: $this->formatPayString($price, $currency),
        );
    }

    /**
     * 失敗した購入をログに記録する
     *
     * 検証失敗・付与失敗のどちらでも呼ばれる。トランザクションの外へ直接書く。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  Receipt  $receiptData  リクエストのレシート情報
     * @param  Verification|null  $verification  レシート検証結果（検証前に失敗した場合はnull）
     * @param  string  $uniqueRequestId  一意なリクエストID
     * @param  Throwable  $error  失敗の原因
     */
    protected function writeFailedPurchaseLog(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Receipt $receiptData,
        ?Verification $verification,
        string $uniqueRequestId,
        Throwable $error
    ): void {
        if ($verification !== null) {
            $price = $this->resolvePurchasePrice($verification, $mstInAppPurchase, $billingPlatform);
            $currency = $this->resolveCurrency($verification, $mstInAppPurchase, $billingPlatform);
        } else {
            // 検証まで到達していないため、マスターの設定値で埋める
            $masterPrice = $this->validationService->findMasterPrice($mstInAppPurchase, $billingPlatform);
            $price = $masterPrice['amount'];
            $currency = $masterPrice['currency'];
        }

        try {
            $this->logInAppPurchaseRepository->insertFailedPurchaseLog(
                uniqueRequestId: $uniqueRequestId,
                sysPlayerId: $sysPlayerId,
                platform: strtolower($platform),
                billingPlatform: $billingPlatform,
                receiptId: (string) ($verification?->getTransactionId() ?? $receiptData->getTransactionId() ?? ''),
                receipt: [
                    'product_id' => $receiptData->getProductId(),
                    'transaction_id' => $receiptData->getTransactionId(),
                    'verification' => $verification?->getRawResponse(),
                    'error' => [
                        'type' => $error::class,
                        'message' => $error->getMessage(),
                    ],
                ],
                mstInAppPurchaseId: (string) $mstInAppPurchase->getId(),
                currencyCode: $currency ?? '',
                payAmount: $price,
                payString: $this->formatPayString($price, $currency),
            );
        } catch (Throwable $loggingError) {
            // ログの書き込み失敗で本来の例外を握りつぶさない
            Log::error('Failed to write the purchase failure log', [
                'unique_request_id' => $uniqueRequestId,
                'sys_player_id' => $sysPlayerId,
                'original_error' => $error->getMessage(),
                'logging_error' => $loggingError->getMessage(),
            ]);
        }
    }

    /**
     * 購入通貨を解決する
     */
    protected function resolveCurrency(
        Verification $verification,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): ?string {
        return $this->validationService->resolvePurchaseCurrency(
            $verification,
            $mstInAppPurchase,
            $billingPlatform
        );
    }

    /**
     * 支払い金額の表示文字列を作る（¥1980, $9.20 など）
     */
    protected function formatPayString(float $price, ?string $currency): string
    {
        return match ($currency) {
            'JPY' => '¥'.number_format($price),
            'USD' => '$'.number_format($price, 2),
            null, '' => number_format($price, 2),
            default => $currency.' '.number_format($price, 2),
        };
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
     * @param  Verification  $verification  レシート検証結果
     */
    abstract protected function executePurchase(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Verification $verification
    ): BuyResponse;
}
