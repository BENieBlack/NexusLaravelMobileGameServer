<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\LeaveGroupResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use NexusChat\Services\ChatService;

/**
 * LeaveGroupUseCase
 *
 * グループチャットを退室する
 */
class LeaveGroupUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function handle(int $sysPlayerId, int $chatRoomId): LeaveGroupResponse
    {
        $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(function () use ($sysPlayerId, $chatRoomId) {
                $this->chatService->leaveGroup($chatRoomId, $sysPlayerId);
            })
        );

        return new LeaveGroupResponse;
    }
}
