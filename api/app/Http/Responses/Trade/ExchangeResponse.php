<?php

namespace App\Http\Responses\Trade;

use App\Http\Responses\_BaseResponse;
use NexusTrade\ValueObjects\TradeProductStatus;

/**
 * ExchangeResponse
 *
 * 交換所の交換レスポンス
 *
 * 交換した後の商品の状態（交換回数・残り回数・1回あたりに手に入るもの）を返す
 */
class ExchangeResponse extends _BaseResponse
{
    public function __construct(
        private readonly TradeProductStatus $tradeProductStatus,
    ) {}

    public static function fromDto(TradeProductStatus $tradeProductStatus): self
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
        ];
    }
}
