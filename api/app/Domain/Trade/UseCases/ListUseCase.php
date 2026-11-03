<?php

namespace App\Domain\Trade\UseCases;

use App\Domain\_BaseUseCase;
use NexusTrade\Services\TradeService;
use NexusTrade\ValueObjects\TradeProductStatus;

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

    /**
     * @return array<int, TradeProductStatus>
     */
    public function exec(int $sysPlayerId): array
    {
        return $this->tradeService->findStatuses($sysPlayerId);
    }
}
