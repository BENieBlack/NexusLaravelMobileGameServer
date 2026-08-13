<?php

namespace App\Http\Controllers;

use App\Domain\InAppPurchase\UseCases\InAppPurchaseBuyDiamondUseCase;
use App\Domain\InAppPurchase\UseCases\InAppPurchaseBuyPackUseCase;
use App\Domain\InAppPurchase\UseCases\InAppPurchaseBuyPassUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Requests\InAppPurchase\BuyRequest;
use App\Repositories\Mst\MstInAppPurchaseRepository;
use Illuminate\Http\JsonResponse;

class InAppPurchaseController extends _BaseController
{
    public function __construct(
        private readonly MstInAppPurchaseRepository $mstInAppPurchaseRepository,
    ) {}

    /**
     * アプリ内課金購入処理
     *
     * 商品タイプに応じてUseCaseを分岐
     */
    public function buy(
        BuyRequest $request,
        InAppPurchaseBuyDiamondUseCase $buyDiamondUseCase,
        InAppPurchaseBuyPackUseCase $buyPackUseCase,
        InAppPurchaseBuyPassUseCase $buyPassUseCase
    ): JsonResponse {
        // 認証情報を取得
        $sysPlayerId = $request->getAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        // リクエストパラメータを取得
        $platform = $request->getPlatform();
        $billingPlatform = $request->getBillingPlatform();
        $receipt = $request->getReceipt();
        $transactionId = $request->getTransactionId();
        $productId = $request->getProductId();

        // 商品マスター取得（Repository経由）
        $product = $this->mstInAppPurchaseRepository->selectActiveById($request->getMstInAppPurchaseId());

        if ($product === null) {
            throw new GameException(
                GameErrorCode::PRODUCT_NOT_FOUND,
                'Product not found or inactive'
            );
        }

        // 商品タイプに応じてUseCaseを選択
        return $this->execute(fn () => match ($product->getType()) {
            'Diamond' => $buyDiamondUseCase->exec($sysPlayerId, $product, $platform, $billingPlatform, $receipt, $transactionId, $productId),
            'Pack' => $buyPackUseCase->exec($sysPlayerId, $product, $platform, $billingPlatform, $receipt, $transactionId, $productId),
            'Pass' => $buyPassUseCase->exec($sysPlayerId, $product, $platform, $billingPlatform, $receipt, $transactionId, $productId),
            default => throw new GameException(
                GameErrorCode::INVALID_PRODUCT_TYPE,
                'Invalid product type'
            ),
        });
    }
}
