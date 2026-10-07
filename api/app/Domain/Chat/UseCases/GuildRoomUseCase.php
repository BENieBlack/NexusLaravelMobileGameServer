<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Repositories\Sys\SysGuildMemberRepository;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\Services\ChatService;

/**
 * GuildRoomUseCase
 *
 * 所属ギルドのチャットルームを取得（無ければ作成）する
 *
 * ギルドチャットはメンバー表を持たないため、加入しているかの判定は
 * ここでしかできない。未加入なら部屋を作らせない。
 */
class GuildRoomUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly SysGuildMemberRepository $sysGuildMemberRepository,
    ) {}

    public function exec(int $sysPlayerId): ChatRoom
    {
        $member = $this->sysGuildMemberRepository->selectByPlayerId($sysPlayerId);

        if ($member === null) {
            throw new GameException(
                GameErrorCode::CHAT_NOT_GUILD_MEMBER,
                'You must be a guild member to use guild chat.',
            );
        }

        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => $this->chatService->findOrCreateGuildRoom($member->getSysGuildId())
            )
        );
    }
}
