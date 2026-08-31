<?php

namespace NexusGuild\Exceptions;

use Exception;

/**
 * GuildException
 *
 * ギルド関連の例外
 */
class GuildException extends Exception
{
    /*
     * 種類を表すコード
     *
     * パッケージはゲーム固有のエラーコード体系を知らないため、
     * ここでは種類だけを持つ。クライアントへ返すコードは
     * アプリケーション層で決める（GuildExceptionTranslator）。
     */

    /** ギルドが見つからない */
    public const CODE_GUILD_NOT_FOUND = 2001;

    /** ギルド名が既に使われている */
    public const CODE_GUILD_NAME_ALREADY_EXISTS = 2002;

    /** ギルドが満員 */
    public const CODE_GUILD_FULL = 2003;

    /** 既にどこかのギルドに所属している */
    public const CODE_ALREADY_IN_GUILD = 2004;

    /** ギルドに所属していない */
    public const CODE_NOT_IN_GUILD = 2005;

    /** 同じギルドへ既に申請している */
    public const CODE_APPLY_ALREADY_EXISTS = 2006;

    /** 申請が見つからない */
    public const CODE_APPLY_NOT_FOUND = 2007;

    /** 申請が決着済みで操作できない */
    public const CODE_INVALID_STATUS = 2008;

    /** 役職の値が不正 */
    public const CODE_INVALID_ROLE = 2009;

    /** 操作する権限が無い */
    public const CODE_PERMISSION_DENIED = 2010;

    /** マスターは脱退できない */
    public const CODE_MASTER_CANNOT_LEAVE = 2011;

    /** メンバーが見つからない */
    public const CODE_MEMBER_NOT_FOUND = 2012;

    /**
     * ギルドが見つからない
     */
    public static function guildNotFound(int $guildId): self
    {
        return new self("Guild not found: {$guildId}", self::CODE_GUILD_NOT_FOUND);
    }

    /**
     * ギルド名が既に存在する
     */
    public static function guildNameAlreadyExists(string $name): self
    {
        return new self("Guild name already exists: {$name}", self::CODE_GUILD_NAME_ALREADY_EXISTS);
    }

    /**
     * ギルドがフル（最大メンバー数に達している）
     */
    public static function guildFull(int $guildId): self
    {
        return new self("Guild is full: {$guildId}", self::CODE_GUILD_FULL);
    }

    /**
     * 既にギルドに所属している
     */
    public static function alreadyInGuild(int $playerId): self
    {
        return new self("Player already in a guild: {$playerId}", self::CODE_ALREADY_IN_GUILD);
    }

    /**
     * ギルドに所属していない
     */
    public static function notInGuild(int $playerId): self
    {
        return new self("Player not in any guild: {$playerId}", self::CODE_NOT_IN_GUILD);
    }

    /**
     * 申請が既に存在する
     */
    public static function applyAlreadyExists(int $guildId, int $playerId): self
    {
        return new self("Apply already exists for guild {$guildId} and player {$playerId}", self::CODE_APPLY_ALREADY_EXISTS);
    }

    /**
     * 申請が見つからない
     */
    public static function applyNotFound(int $applyId): self
    {
        return new self("Apply not found: {$applyId}", self::CODE_APPLY_NOT_FOUND);
    }

    /**
     * 無効なステータス
     */
    public static function invalidStatus(string $status): self
    {
        return new self("Invalid status: {$status}", self::CODE_INVALID_STATUS);
    }

    /**
     * 無効な役職
     */
    public static function invalidRole(string $role): self
    {
        return new self("Invalid role: {$role}", self::CODE_INVALID_ROLE);
    }

    /**
     * 権限がない
     */
    public static function permissionDenied(int $playerId, string $action): self
    {
        return new self("Player {$playerId} does not have permission to {$action}", self::CODE_PERMISSION_DENIED);
    }

    /**
     * マスターは脱退できない
     */
    public static function masterCannotLeave(int $playerId): self
    {
        return new self("Master cannot leave the guild: {$playerId}", self::CODE_MASTER_CANNOT_LEAVE);
    }

    /**
     * メンバーが見つからない
     */
    public static function memberNotFound(int $playerId): self
    {
        return new self("Member not found: {$playerId}", self::CODE_MEMBER_NOT_FOUND);
    }
}
