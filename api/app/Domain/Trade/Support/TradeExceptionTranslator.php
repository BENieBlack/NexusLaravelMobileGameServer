<?php

namespace App\Domain\Trade\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use Closure;
use NexusTrade\Exceptions\TradeException;

/**
 * TradeExceptionTranslator
 *
 * パッケージの TradeException を GameException へ翻訳する
 */
class TradeExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        TradeException::CODE_PRODUCT_NOT_FOUND => GameErrorCode::TRADE_PRODUCT_NOT_FOUND,
        TradeException::CODE_PRODUCT_NOT_OPEN => GameErrorCode::TRADE_PRODUCT_NOT_OPEN,
        TradeException::CODE_TRADE_LIMIT_EXCEEDED => GameErrorCode::TRADE_LIMIT_EXCEEDED,
        TradeException::CODE_INVALID_QUANTITY => GameErrorCode::INVALID_PARAMETER,
    ];

    /**
     * 処理を実行し、TradeException が出たら GameException へ翻訳する
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function translate(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (TradeException $e) {
            throw new GameException(
                self::ERROR_CODE_MAP[$e->getErrorCode()] ?? GameErrorCode::INVALID_PARAMETER,
                $e->getMessage(),
            );
        }
    }
}
