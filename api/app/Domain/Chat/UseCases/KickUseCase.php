<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\KickResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
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

    public function handle(int $sysPlayerId, int $chatRoomId, int $targetSysPlayerId): KickResponse
    {
        $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(function () use ($sysPlayerId, $chatRoomId, $targetSysPlayerId) {
                $this->chatService->kickFromGroup($chatRoomId, $sysPlayerId, $targetSysPlayerId);
            })
        );

        return new KickResponse;
    }
}
