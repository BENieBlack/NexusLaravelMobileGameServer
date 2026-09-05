<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
use App\Http\Responses\Chat\MemberResponse;
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

    public function exec(int $sysPlayerId, int $chatRoomId, int $targetSysPlayerId): MemberResponse
    {
        $targetName = $this->nameResolver->resolve($targetSysPlayerId);

        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => new MemberResponse(
                    $this->chatService->inviteToGroup($chatRoomId, $sysPlayerId, $targetSysPlayerId, $targetName)
                )
            )
        );
    }
}
