<?php

namespace App\Domain\Friend\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusFriend\Exceptions\FriendException;

/**
 * FriendExceptionTranslator
 *
 * パッケージのFriendExceptionをGameExceptionへ翻訳する
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、
 * 種類だけを持った例外を投げる。クライアントへ返すコードは
 * アプリケーション層のここで決める。
 */
class FriendExceptionTranslator
{
    /**
     * パッケージのエラーコード => クライアントへ返すエラーコード
     */
    private const ERROR_CODE_MAP = [
        FriendException::CODE_SELF_APPLY => GameErrorCode::CANNOT_SEND_FRIEND_REQUEST_TO_SELF,
        FriendException::CODE_ALREADY_APPLIED => GameErrorCode::FRIEND_REQUEST_ALREADY_EXISTS,
        FriendException::CODE_ALREADY_FRIENDS => GameErrorCode::FRIEND_ALREADY_EXISTS,
        FriendException::CODE_APPLY_NOT_FOUND => GameErrorCode::FRIEND_APPLY_NOT_FOUND,
        FriendException::CODE_NOT_AUTHORIZED => GameErrorCode::NOT_AUTHORIZED_TO_ACCEPT,
        FriendException::CODE_ALREADY_ACCEPTED => GameErrorCode::FRIEND_APPLY_ALREADY_ACCEPTED,
        FriendException::CODE_ALREADY_REJECTED => GameErrorCode::FRIEND_APPLY_ALREADY_REJECTED,
        FriendException::CODE_ALREADY_DELETED => GameErrorCode::FRIEND_APPLY_ALREADY_DELETED,
        FriendException::CODE_FRIEND_NOT_FOUND => GameErrorCode::FRIEND_NOT_FOUND,
    ];

    /**
     * 処理を実行し、FriendExceptionが出たらGameExceptionへ翻訳する
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function translate(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (FriendException $e) {
            throw new GameException(self::resolveErrorCode($e), $e->getMessage());
        }
    }

    /**
     * 却下の権限エラーだけはコードが分かれているため、個別に翻訳する
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function translateForReject(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (FriendException $e) {
            $errorCode = $e->getCode() === FriendException::CODE_NOT_AUTHORIZED
                ? GameErrorCode::NOT_AUTHORIZED_TO_REJECT
                : self::resolveErrorCode($e);

            throw new GameException($errorCode, $e->getMessage());
        }
    }

    /**
     * 削除では「自分自身」のコードが申請時と分かれているため、個別に翻訳する
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function translateForDelete(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (FriendException $e) {
            $errorCode = $e->getCode() === FriendException::CODE_SELF_APPLY
                ? GameErrorCode::CANNOT_DELETE_SELF
                : self::resolveErrorCode($e);

            throw new GameException($errorCode, $e->getMessage());
        }
    }

    private static function resolveErrorCode(FriendException $e): int
    {
        return self::ERROR_CODE_MAP[$e->getCode()] ?? GameErrorCode::FRIEND_APPLY_NOT_FOUND;
    }
}
