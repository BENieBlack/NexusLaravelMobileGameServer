<?php

namespace App\Domain\Mission\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use Closure;
use NexusMission\Exceptions\MissionException;

/**
 * MissionExceptionTranslator
 *
 * パッケージのMissionExceptionをGameExceptionへ翻訳する
 *
 * 翻訳しないと、未達成や二重受け取りといった業務エラーがHTTP 500になり、
 * クライアントはサーバ障害と区別できない。
 */
class MissionExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        'MISSION_NOT_FOUND' => GameErrorCode::MISSION_NOT_FOUND,
        'MISSION_NOT_OPEN' => GameErrorCode::MISSION_NOT_OPEN,
        'MISSION_NOT_COMPLETED' => GameErrorCode::MISSION_NOT_COMPLETED,
        'MISSION_ALREADY_RECEIVED' => GameErrorCode::MISSION_ALREADY_RECEIVED,
    ];

    /**
     * 処理を実行し、MissionExceptionが出たらGameExceptionへ翻訳する
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
        } catch (MissionException $e) {
            throw new GameException(
                self::ERROR_CODE_MAP[$e->getErrorCode()] ?? GameErrorCode::INVALID_PARAMETER,
                $e->getMessage(),
            );
        }
    }
}
