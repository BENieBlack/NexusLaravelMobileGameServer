<?php

namespace App\Domain\Chat\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use Closure;
use NexusChat\Exceptions\ChatException;

/**
 * ChatExceptionTranslator
 *
 * パッケージのChatExceptionをGameExceptionへ翻訳する
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、
 * 種類だけを持った例外を投げる。翻訳しないとHTTP 500になり、
 * クライアントは「権限が無い」と「サーバ障害」を区別できない。
 */
class ChatExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        'CHAT_ROOM_NOT_FOUND' => GameErrorCode::CHAT_ROOM_NOT_FOUND,
        'CHAT_NOT_ROOM_MEMBER' => GameErrorCode::CHAT_NOT_ROOM_MEMBER,
        'CHAT_MESSAGE_TOO_LONG' => GameErrorCode::CHAT_MESSAGE_TOO_LONG,
        'CHAT_MESSAGE_EMPTY' => GameErrorCode::CHAT_MESSAGE_EMPTY,
        'CHAT_NOT_MESSAGE_OWNER' => GameErrorCode::CHAT_NOT_MESSAGE_OWNER,
        'CHAT_NOT_FRIENDS' => GameErrorCode::CHAT_NOT_FRIENDS,
        'CHAT_NOT_GUILD_MEMBER' => GameErrorCode::CHAT_NOT_GUILD_MEMBER,
        'CHAT_NO_INVITE_PERMISSION' => GameErrorCode::CHAT_NO_INVITE_PERMISSION,
        'CHAT_NO_KICK_PERMISSION' => GameErrorCode::CHAT_NO_KICK_PERMISSION,
        'CHAT_NO_ROLE_MANAGE_PERMISSION' => GameErrorCode::CHAT_NO_ROLE_MANAGE_PERMISSION,
        'CHAT_ROOM_FULL' => GameErrorCode::CHAT_ROOM_FULL,
        'CHAT_ALREADY_MEMBER' => GameErrorCode::CHAT_ALREADY_MEMBER,
        'CHAT_CANNOT_KICK_OWNER' => GameErrorCode::CHAT_CANNOT_KICK_OWNER,
    ];

    /**
     * 処理を実行し、ChatExceptionが出たらGameExceptionへ翻訳する
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
        } catch (ChatException $e) {
            throw new GameException(
                self::ERROR_CODE_MAP[$e->getErrorCode()] ?? GameErrorCode::INVALID_PARAMETER,
                $e->getMessage(),
            );
        }
    }
}
