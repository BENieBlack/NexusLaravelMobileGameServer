<?php

namespace App\Domain\Guild\Support;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusGuild\Exceptions\GuildException;

/**
 * GuildExceptionTranslator
 *
 * パッケージのGuildExceptionをGameExceptionへ翻訳する
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、
 * 種類だけを持った例外を投げる。クライアントへ返すコードは
 * アプリケーション層のここで決める。
 *
 * 同じ種類でも操作によって返すコードが変わる（作成時の
 * 「既に存在する」はギルド名の重複、申請時は申請の重複）ため、
 * 操作ごとに入口を分けている。
 */
class GuildExceptionTranslator
{
    /**
     * どの操作でも共通の対応
     */
    private const COMMON_MAP = [
        GuildException::CODE_GUILD_NOT_FOUND => GameErrorCode::GUILD_NOT_FOUND,
        GuildException::CODE_GUILD_FULL => GameErrorCode::GUILD_FULL,
        GuildException::CODE_ALREADY_IN_GUILD => GameErrorCode::PLAYER_ALREADY_IN_GUILD,
        GuildException::CODE_NOT_IN_GUILD => GameErrorCode::PLAYER_NOT_IN_GUILD,
        GuildException::CODE_APPLY_NOT_FOUND => GameErrorCode::GUILD_APPLY_NOT_FOUND,
        GuildException::CODE_INVALID_STATUS => GameErrorCode::GUILD_INVALID_STATUS,
        GuildException::CODE_PERMISSION_DENIED => GameErrorCode::GUILD_PERMISSION_DENIED,
        GuildException::CODE_MASTER_CANNOT_LEAVE => GameErrorCode::GUILD_MASTER_CANNOT_LEAVE,
        GuildException::CODE_MEMBER_NOT_FOUND => GameErrorCode::GUILD_MEMBER_NOT_FOUND,
    ];

    /**
     * ギルド作成
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function forCreate(\Closure $callback): mixed
    {
        return self::run($callback, [
            // 作成時の「既に存在する」はギルド名の重複
            GuildException::CODE_GUILD_NAME_ALREADY_EXISTS => GameErrorCode::GUILD_NAME_ALREADY_EXISTS,
        ], GameErrorCode::GUILD_CREATE_FAILED);
    }

    /**
     * 加入申請の送信
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function forApplySend(\Closure $callback): mixed
    {
        return self::run($callback, [
            // 申請時の「既に存在する」は申請の重複
            GuildException::CODE_APPLY_ALREADY_EXISTS => GameErrorCode::GUILD_APPLY_ALREADY_EXISTS,
        ], GameErrorCode::GUILD_APPLY_FAILED);
    }

    /**
     * 加入申請の承認
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function forApplyAccept(\Closure $callback): mixed
    {
        return self::run($callback, [], GameErrorCode::GUILD_APPLY_ACCEPT_FAILED);
    }

    /**
     * 加入申請の却下
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function forApplyReject(\Closure $callback): mixed
    {
        return self::run($callback, [], GameErrorCode::GUILD_APPLY_REJECT_FAILED);
    }

    /**
     * 脱退
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function forLeave(\Closure $callback): mixed
    {
        return self::run($callback, [], GameErrorCode::GUILD_LEAVE_FAILED);
    }

    /**
     * 参照系（一覧・詳細）
     *
     * 更新を伴わないため、想定外はサーバ側の問題として扱う。
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     *
     * @throws GameException
     */
    public static function forRead(\Closure $callback): mixed
    {
        return self::run($callback, [], GameErrorCode::INTERNAL_ERROR);
    }

    /**
     * 共通の対応表に操作ごとの上書きを重ねて翻訳する
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @param  array<int, int>  $overrides  操作固有の対応
     * @param  int  $fallback  対応が無い場合に返すコード
     * @return T
     *
     * @throws GameException
     */
    private static function run(\Closure $callback, array $overrides, int $fallback): mixed
    {
        try {
            return $callback();
        } catch (GuildException $e) {
            $map = $overrides + self::COMMON_MAP;

            throw new GameException($map[$e->getCode()] ?? $fallback, $e->getMessage());
        }
    }
}
