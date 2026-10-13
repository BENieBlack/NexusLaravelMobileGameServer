<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use NexusChat\DataTransferObjects\ChatRoom;
use NexusChat\Services\ChatService;

/**
 * RoomsUseCase
 *
 * 参加中のルーム一覧
 */
class RoomsUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    /**
     * @return array<ChatRoom>
     */
    public function exec(int $sysPlayerId): array
    {
        return $this->chatService->getRoomsByPlayer($sysPlayerId);
    }
}
