<?php

namespace App\Http\Requests\Trade;

use App\Http\Requests\_BaseRequest;

/**
 * ExchangeRequest
 *
 * POST /api/trade/exchange のリクエスト
 */
class ExchangeRequest extends _BaseRequest
{
    /**
     * 1回のリクエストで交換できる個数の上限（上限の無い商品をまとめて交換するときの歯止め）
     */
    private const MAX_QUANTITY = 999;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_trade_product_id' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    public function getMstTradeProductId(): string
    {
        return (string) $this->input('mst_trade_product_id');
    }

    public function getQuantity(): int
    {
        return (int) $this->input('quantity');
    }
}
