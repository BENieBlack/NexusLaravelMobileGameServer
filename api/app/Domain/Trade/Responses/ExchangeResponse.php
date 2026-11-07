<?php

namespace App\Domain\Trade\Responses;

use App\Domain\_BaseResponse;
use App\Domain\ReturnsUpdatedResources;
use App\Traits\HasUpdatedResources;
use NexusTrade\ValueObjects\TradeProductStatus;

/**
 * ExchangeResponse
 *
 * 交換所の交換レスポンス
 *
 * 交換した後の商品の状態（交換回数・残り回数・1回あたりに手に入るもの）を返す
 */
class ExchangeResponse extends _BaseResponse implements ReturnsUpdatedResources
{
    use HasUpdatedResources;

    public function __construct(
        private readonly TradeProductStatus $tradeProductStatus,
    ) {}

    public static function from(TradeProductStatus $tradeProductStatus): self
    {
        return new self($tradeProductStatus);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trade_product' => $this->tradeProductStatus->toArray(),
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];
    }
}
