<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Http\Responses\Chat\MemberListResponse;
use NexusChat\Services\ChatService;

/**
 * GroupMembersUseCase
 *
 * グループチャットのメンバー一覧
 */
class GroupMembersUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function exec(int $sysPlayerId, int $chatRoomId): MemberListResponse
    {
        return ChatExceptionTranslator::translate(
            fn () => new MemberListResponse($this->chatService->getGroupMembers($chatRoomId, $sysPlayerId))
        );
    }
}
