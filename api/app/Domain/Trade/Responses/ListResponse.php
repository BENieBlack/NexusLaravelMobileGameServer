<?php

namespace App\Domain\Trade\Responses;

use App\Domain\_BaseResponse;
use NexusTrade\ValueObjects\TradeProductStatus;

/**
 * ListResponse
 *
 * 交換所の商品一覧レスポンス
 *
 * 開催中の交換所に並ぶ商品の、コスト・交換回数・手に入るものを返す。
 * 交換所や商品の名前はクライアントがマスターデータ（mst_trade__l10n / mst_trade_product__l10n）から引く。
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  array<int, TradeProductStatus>  $tradeProductStatuses
     */
    public function __construct(
        private readonly array $tradeProductStatuses,
    ) {}

    /**
     * @param  array<int, TradeProductStatus>  $tradeProductStatuses
     */
    public static function from(array $tradeProductStatuses): self
    {
        return new self($tradeProductStatuses);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trade_product_list' => array_map(
                fn (TradeProductStatus $tradeProductStatus) => $tradeProductStatus->toArray(),
                $this->tradeProductStatuses,
            ),
        ];
    }
}
