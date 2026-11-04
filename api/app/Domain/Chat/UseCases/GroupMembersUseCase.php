<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\GroupMembersResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
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

    public function handle(int $sysPlayerId, int $chatRoomId): GroupMembersResponse
    {
        $chatRoomMembers = ChatExceptionTranslator::translate(
            fn () => $this->chatService->getGroupMembers($chatRoomId, $sysPlayerId)
        );

        return GroupMembersResponse::from($chatRoomMembers);
    }
}
