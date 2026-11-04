<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\InviteResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
use NexusChat\Services\ChatService;

/**
 * InviteUseCase
 *
 * グループチャットへメンバーを招待する
 */
class InviteUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ChatPlayerNameResolver $nameResolver,
    ) {}

    public function handle(int $sysPlayerId, int $chatRoomId, int $targetSysPlayerId): InviteResponse
    {
        $targetName = $this->nameResolver->resolve($targetSysPlayerId);

        $chatRoomMember = $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => $this->chatService->inviteToGroup($chatRoomId, $sysPlayerId, $targetSysPlayerId, $targetName)
            )
        );

        return InviteResponse::from($chatRoomMember);
    }
}
