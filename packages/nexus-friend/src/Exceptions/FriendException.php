<?php

namespace NexusFriend\Exceptions;

/**
 * FriendException
 *
 * フレンド機能で発生するエラーを表す例外クラス
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、
 * ここでは種類だけを示す。HTTPへ返すコードへの翻訳は
 * アプリケーション層が行う。
 */
class FriendException extends \RuntimeException
{
    /** 自分自身への申請 */
    public const CODE_SELF_APPLY = 1001;

    /** 既に申請中 */
    public const CODE_ALREADY_APPLIED = 1002;

    /** 既にフレンド */
    public const CODE_ALREADY_FRIENDS = 1003;

    /** 申請が見つからない */
    public const CODE_APPLY_NOT_FOUND = 1004;

    /** 承認/却下の権限がない */
    public const CODE_NOT_AUTHORIZED = 1005;

    /** 既に承認済み */
    public const CODE_ALREADY_ACCEPTED = 1006;

    /** 既に却下済み */
    public const CODE_ALREADY_REJECTED = 1007;

    /** 既に削除済み */
    public const CODE_ALREADY_DELETED = 1008;

    /** フレンド関係が見つからない */
    public const CODE_FRIEND_NOT_FOUND = 1009;

    public static function selfApply(): self
    {
        return new self('Cannot send friend request to yourself', self::CODE_SELF_APPLY);
    }

    public static function alreadyApplied(): self
    {
        return new self('Friend request already exists', self::CODE_ALREADY_APPLIED);
    }

    public static function alreadyFriends(): self
    {
        return new self('Already friends', self::CODE_ALREADY_FRIENDS);
    }

    public static function applyNotFound(): self
    {
        return new self('Friend apply not found', self::CODE_APPLY_NOT_FOUND);
    }

    public static function notAuthorized(): self
    {
        return new self('Not authorized to accept/reject this request', self::CODE_NOT_AUTHORIZED);
    }

    public static function alreadyAccepted(): self
    {
        return new self('Friend request already accepted', self::CODE_ALREADY_ACCEPTED);
    }

    public static function alreadyRejected(): self
    {
        return new self('Friend request already rejected', self::CODE_ALREADY_REJECTED);
    }

    public static function alreadyDeleted(): self
    {
        return new self('Friend request already deleted', self::CODE_ALREADY_DELETED);
    }

    public static function friendNotFound(): self
    {
        return new self('Friend not found', self::CODE_FRIEND_NOT_FOUND);
    }
}
