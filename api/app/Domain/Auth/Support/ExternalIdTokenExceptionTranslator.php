<?php

namespace App\Domain\Auth\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use Closure;
use NexusAuth\Exceptions\ExternalIdTokenException;

/**
 * ExternalIdTokenExceptionTranslator
 *
 * パッケージの ExternalIdTokenException を GameException へ翻訳する
 */
class ExternalIdTokenExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        ExternalIdTokenException::CODE_UNSUPPORTED_PROVIDER => GameErrorCode::EXTERNAL_PROVIDER_UNAVAILABLE,
        ExternalIdTokenException::CODE_INVALID_TOKEN => GameErrorCode::EXTERNAL_ID_TOKEN_INVALID,
    ];

    /**
     * 処理を実行し、ExternalIdTokenException が出たら GameException へ翻訳する
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
        } catch (ExternalIdTokenException $e) {
            throw new GameException(
                self::ERROR_CODE_MAP[$e->getErrorCode()] ?? GameErrorCode::EXTERNAL_ID_TOKEN_INVALID,
                $e->getMessage(),
            );
        }
    }
}
