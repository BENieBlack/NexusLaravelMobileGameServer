<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Http\Responses\Chat\ResultResponse;
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

    public function exec(int $sysPlayerId, int $chatRoomId): ResultResponse
    {
        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(function () use ($sysPlayerId, $chatRoomId) {
                $this->chatService->leaveGroup($chatRoomId, $sysPlayerId);

                return new ResultResponse;
            })
        );
    }
}
