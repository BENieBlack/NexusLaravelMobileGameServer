<?php

namespace NexusTrade\Exceptions;

use RuntimeException;

/**
 * TradeException
 *
 * 交換所のドメイン例外
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、種類だけを持つ。
 * クライアントへ返すコードへの変換はアプリケーション層の
 * TradeExceptionTranslator が行う。
 */
class TradeException extends RuntimeException
{
    public const CODE_PRODUCT_NOT_FOUND = 'PRODUCT_NOT_FOUND';

    public const CODE_PRODUCT_NOT_OPEN = 'PRODUCT_NOT_OPEN';

    public const CODE_TRADE_LIMIT_EXCEEDED = 'TRADE_LIMIT_EXCEEDED';

    public const CODE_INVALID_QUANTITY = 'INVALID_QUANTITY';

    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function productNotFound(string $mstTradeProductId): self
    {
        return new self(self::CODE_PRODUCT_NOT_FOUND, "商品が見つかりません: {$mstTradeProductId}");
    }

    /**
     * 商品か、商品を置いている交換所が交換期間外
     */
    public static function productNotOpen(string $mstTradeProductId): self
    {
        return new self(self::CODE_PRODUCT_NOT_OPEN, "交換期間外の商品です: {$mstTradeProductId}");
    }

    public static function tradeLimitExceeded(string $mstTradeProductId, int $tradeLimit, int $tradedCount, int $quantity): self
    {
        return new self(
            self::CODE_TRADE_LIMIT_EXCEEDED,
            "交換回数の上限を超えます: {$mstTradeProductId} 上限={$tradeLimit} 交換済み={$tradedCount} 今回={$quantity}",
        );
    }

    public static function invalidQuantity(int $quantity): self
    {
        return new self(self::CODE_INVALID_QUANTITY, "交換する個数は1以上にしてください: {$quantity}");
    }
}
