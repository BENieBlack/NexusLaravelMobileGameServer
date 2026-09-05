<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Http\Responses\Chat\ResultResponse;
use NexusChat\Services\ChatService;

/**
 * KickUseCase
 *
 * グループチャットからメンバーを外す
 */
class KickUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function exec(int $sysPlayerId, int $chatRoomId, int $targetSysPlayerId): ResultResponse
    {
        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(function () use ($sysPlayerId, $chatRoomId, $targetSysPlayerId) {
                $this->chatService->kickFromGroup($chatRoomId, $sysPlayerId, $targetSysPlayerId);

                return new ResultResponse;
            })
        );
    }
}
