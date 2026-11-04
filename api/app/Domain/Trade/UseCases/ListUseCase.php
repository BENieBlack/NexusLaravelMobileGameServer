<?php

namespace App\Domain\Trade\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Trade\Responses\ListResponse;
use NexusTrade\Services\TradeService;

/**
 * ListUseCase
 *
 * 開催中の交換所に並んでいる商品と、今の周期での交換回数を返す
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly TradeService $tradeService,
    ) {}

    public function handle(int $sysPlayerId): ListResponse
    {
        return ListResponse::from($this->tradeService->findStatuses($sysPlayerId));
    }
}
