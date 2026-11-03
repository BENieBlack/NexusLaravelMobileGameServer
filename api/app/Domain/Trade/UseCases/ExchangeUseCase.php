<?php

namespace App\Domain\Trade\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Trade\Support\TradeExceptionTranslator;
use App\Exceptions\GameException;
use App\Repositories\Log\LogTradeRepository;
use NexusTrade\Services\TradeService;
use NexusTrade\ValueObjects\TradeProductStatus;

/**
 * ExchangeUseCase
 *
 * 交換所の商品を、コストを支払って交換する
 */
class ExchangeUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly TradeService $tradeService,
        private readonly LogTradeRepository $logTradeRepository,
    ) {}

    /**
     * @param  int  $quantity  交換する個数
     * @param  string|null  $uniqueRequestId  ログをリクエスト単位で追うためのID（X-Unique-Request-Identifier）
     * @return TradeProductStatus 交換した後の状態（手に入れたものを含む）
     *
     * @throws GameException 存在しない・交換期間外・上限を超える・コストが足りない場合
     */
    public function exec(int $sysPlayerId, string $mstTradeProductId, int $quantity, ?string $uniqueRequestId): TradeProductStatus
    {
        if ($uniqueRequestId !== null) {
            $this->logTradeRepository->setUniqueRequestId($uniqueRequestId);
        }

        return $this->executeWithTransaction(
            fn () => TradeExceptionTranslator::translate(
                fn () => $this->tradeService->trade($sysPlayerId, $mstTradeProductId, $quantity)
            )
        );
    }
}
