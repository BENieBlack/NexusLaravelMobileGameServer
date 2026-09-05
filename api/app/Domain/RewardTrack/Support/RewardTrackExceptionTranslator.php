<?php

namespace App\Domain\RewardTrack\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use Closure;
use NexusRewardTrack\Exceptions\RewardTrackException;

/**
 * RewardTrackExceptionTranslator
 *
 * パッケージのRewardTrackExceptionをGameExceptionへ翻訳する
 *
 * 翻訳しないと、進捗不足や二重受け取りといった業務エラーがHTTP 500になり、
 * クライアントはサーバ障害と区別できない。
 */
class RewardTrackExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        'REWARD_TRACK_NOT_FOUND' => GameErrorCode::REWARD_TRACK_NOT_FOUND,
        'REWARD_TRACK_NOT_STARTED' => GameErrorCode::REWARD_TRACK_NOT_STARTED,
        'REWARD_TRACK_ENDED' => GameErrorCode::REWARD_TRACK_ENDED,
        'REWARD_TRACK_LINE_NOT_FOUND' => GameErrorCode::REWARD_TRACK_LINE_NOT_FOUND,
        'REWARD_TRACK_FREE_LINE_NOT_PURCHASABLE' => GameErrorCode::REWARD_TRACK_FREE_LINE_NOT_PURCHASABLE,
        'REWARD_TRACK_LINE_ALREADY_OWNED' => GameErrorCode::REWARD_TRACK_LINE_ALREADY_OWNED,
        'REWARD_TRACK_LINE_NOT_OWNED' => GameErrorCode::REWARD_TRACK_LINE_NOT_OWNED,
        'REWARD_TRACK_MILESTONE_NOT_FOUND' => GameErrorCode::REWARD_TRACK_MILESTONE_NOT_FOUND,
        'REWARD_TRACK_PROGRESS_NOT_ENOUGH' => GameErrorCode::REWARD_TRACK_PROGRESS_NOT_ENOUGH,
        'REWARD_TRACK_ALREADY_RECEIVED' => GameErrorCode::REWARD_TRACK_ALREADY_RECEIVED,
    ];

    /**
     * 処理を実行し、RewardTrackExceptionが出たらGameExceptionへ翻訳する
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
        } catch (RewardTrackException $e) {
            throw new GameException(
                self::ERROR_CODE_MAP[$e->getErrorCode()] ?? GameErrorCode::INVALID_PARAMETER,
                $e->getMessage(),
            );
        }
    }
}
