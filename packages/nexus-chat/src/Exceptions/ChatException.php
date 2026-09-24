<?php

namespace NexusChat\Exceptions;

use RuntimeException;

/**
 * ChatException
 *
 * チャット機能のドメイン例外
 */
class ChatException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function roomNotFound(): self
    {
        return new self('CHAT_ROOM_NOT_FOUND', 'Chat room not found.');
    }

    public static function notRoomMember(): self
    {
        return new self('CHAT_NOT_ROOM_MEMBER', 'You are not a member of this chat room.');
    }

    public static function messageTooLong(): self
    {
        return new self('CHAT_MESSAGE_TOO_LONG', 'Message exceeds the maximum length.');
    }

    public static function messageEmpty(): self
    {
        return new self('CHAT_MESSAGE_EMPTY', 'Message body cannot be empty.');
    }

    public static function notMessageOwner(): self
    {
        return new self('CHAT_NOT_MESSAGE_OWNER', 'You can only delete your own messages.');
    }

    public static function notFriends(): self
    {
        return new self('CHAT_NOT_FRIENDS', 'You must be friends to send a direct message.');
    }

    public static function notGuildMember(): self
    {
        return new self('CHAT_NOT_GUILD_MEMBER', 'You must be a guild member to use guild chat.');
    }

    public static function noInvitePermission(): self
    {
        return new self('CHAT_NO_INVITE_PERMISSION', 'You do not have permission to invite members.');
    }

    public static function noKickPermission(): self
    {
        return new self('CHAT_NO_KICK_PERMISSION', 'You do not have permission to kick members.');
    }

    public static function noRoleManagePermission(): self
    {
        return new self('CHAT_NO_ROLE_MANAGE_PERMISSION', 'Only the owner can manage roles.');
    }

    public static function roomFull(): self
    {
        return new self('CHAT_ROOM_FULL', 'The chat room has reached the maximum number of members.');
    }

    public static function alreadyMember(): self
    {
        return new self('CHAT_ALREADY_MEMBER', 'The player is already a member of this chat room.');
    }

    public static function cannotKickOwner(): self
    {
        return new self('CHAT_CANNOT_KICK_OWNER', 'The owner cannot be kicked from the room.');
    }
}
