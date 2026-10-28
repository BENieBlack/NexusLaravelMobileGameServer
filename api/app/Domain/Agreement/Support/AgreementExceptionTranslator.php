<?php

namespace App\Domain\Agreement\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use Closure;
use NexusAgreement\Exceptions\AgreementException;

/**
 * AgreementExceptionTranslator
 *
 * パッケージの AgreementException を GameException へ翻訳する
 */
class AgreementExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        AgreementException::CODE_AGREEMENT_NOT_FOUND => GameErrorCode::AGREEMENT_NOT_FOUND,
        AgreementException::CODE_ALREADY_CONSENTED => GameErrorCode::AGREEMENT_ALREADY_CONSENTED,
    ];

    /**
     * 処理を実行し、AgreementException が出たら GameException へ翻訳する
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
        } catch (AgreementException $e) {
            throw new GameException(
                self::ERROR_CODE_MAP[$e->getErrorCode()] ?? GameErrorCode::INVALID_PARAMETER,
                $e->getMessage(),
            );
        }
    }
}
