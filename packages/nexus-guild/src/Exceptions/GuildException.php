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
    /**
     * ギルドが見つからない
     */
    public static function guildNotFound(int $guildId): self
    {
        return new self("Guild not found: {$guildId}");
    }

    /**
     * ギルド名が既に存在する
     */
    public static function guildNameAlreadyExists(string $name): self
    {
        return new self("Guild name already exists: {$name}");
    }

    /**
     * ギルドがフル（最大メンバー数に達している）
     */
    public static function guildFull(int $guildId): self
    {
        return new self("Guild is full: {$guildId}");
    }

    /**
     * 既にギルドに所属している
     */
    public static function alreadyInGuild(int $playerId): self
    {
        return new self("Player already in a guild: {$playerId}");
    }

    /**
     * ギルドに所属していない
     */
    public static function notInGuild(int $playerId): self
    {
        return new self("Player not in any guild: {$playerId}");
    }

    /**
     * 申請が既に存在する
     */
    public static function applyAlreadyExists(int $guildId, int $playerId): self
    {
        return new self("Apply already exists for guild {$guildId} and player {$playerId}");
    }

    /**
     * 申請が見つからない
     */
    public static function applyNotFound(int $applyId): self
    {
        return new self("Apply not found: {$applyId}");
    }

    /**
     * 無効なステータス
     */
    public static function invalidStatus(string $status): self
    {
        return new self("Invalid status: {$status}");
    }

    /**
     * 無効な役職
     */
    public static function invalidRole(string $role): self
    {
        return new self("Invalid role: {$role}");
    }

    /**
     * 権限がない
     */
    public static function permissionDenied(int $playerId, string $action): self
    {
        return new self("Player {$playerId} does not have permission to {$action}");
    }

    /**
     * マスターは脱退できない
     */
    public static function masterCannotLeave(int $playerId): self
    {
        return new self("Master cannot leave the guild: {$playerId}");
    }

    /**
     * メンバーが見つからない
     */
    public static function memberNotFound(int $playerId): self
    {
        return new self("Member not found: {$playerId}");
    }
}
