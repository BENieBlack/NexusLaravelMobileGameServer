<?php

namespace NexusTrade\Contracts;

use NexusTrade\DataTransferObjects\Trade;
use NexusTrade\DataTransferObjects\TradeProduct;
use NexusTrade\DataTransferObjects\TradeProductContent;

/**
 * TradeMasterRepositoryInterface
 *
 * 交換所のマスターデータ（mst_trade / mst_trade_product / mst_trade_product_content）を読む
 */
interface TradeMasterRepositoryInterface
{
    /**
     * @return array<int, Trade>
     */
    public function selectAllTrades(): array;

    public function selectTradeById(string $mstTradeId): ?Trade;

    /**
     * @return array<int, TradeProduct>
     */
    public function selectAllProducts(): array;

    public function selectProductById(string $mstTradeProductId): ?TradeProduct;

    /**
     * 商品を1回交換すると手に入るもの（sort_order昇順）
     *
     * @return array<int, TradeProductContent>
     */
    public function selectContentsByProductId(string $mstTradeProductId): array;
}
