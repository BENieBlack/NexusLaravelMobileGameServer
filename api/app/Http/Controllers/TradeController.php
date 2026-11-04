<?php

namespace App\Http\Controllers;

use App\Domain\Trade\UseCases\ExchangeUseCase;
use App\Domain\Trade\UseCases\ListUseCase;
use App\Http\Requests\Trade\ExchangeRequest;
use App\Http\Requests\Trade\ListRequest;
use App\Http\Responses\Trade\ExchangeResponse;
use App\Http\Responses\Trade\ListResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * TradeController
 *
 * 交換所のAPIエンドポイント
 *
 * ダイヤ・コイン・アイテムを支払って、アイテム・ユニット・装備などと交換する
 */
class TradeController extends _BaseController
{
    public function __construct(
        private readonly ListUseCase $listUseCase,
        private readonly ExchangeUseCase $exchangeUseCase,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * 交換所の商品一覧
     *
     * GET /api/trade/list
     */
    public function list(ListRequest $request): JsonResponse
    {
        return $this->execute(fn () => ListResponse::fromResult(
            $this->listUseCase->handle($this->apiSession->getSysPlayerId())
        ));
    }

    /**
     * 商品を交換する
     *
     * POST /api/trade/exchange
     */
    public function exchange(ExchangeRequest $request): JsonResponse
    {
        return $this->execute(fn () => ExchangeResponse::fromResult($this->exchangeUseCase->handle(
            sysPlayerId: $this->apiSession->getSysPlayerId(),
            mstTradeProductId: $request->getMstTradeProductId(),
            quantity: $request->getQuantity(),
            uniqueRequestId: $request->getUniqueRequestIdentifier(),
        )));
    }
}
