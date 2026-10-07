<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use NexusChat\DataTransferObjects\ChatRoomMember;
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

    /**
     * @return array<ChatRoomMember>
     */
    public function exec(int $sysPlayerId, int $chatRoomId): array
    {
        return ChatExceptionTranslator::translate(
            fn () => $this->chatService->getGroupMembers($chatRoomId, $sysPlayerId)
        );
    }
}
