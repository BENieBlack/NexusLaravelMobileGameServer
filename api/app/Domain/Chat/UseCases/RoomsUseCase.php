<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Chat\RoomListResponse;
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

    public function exec(int $sysPlayerId): RoomListResponse
    {
        return new RoomListResponse($this->chatService->getRoomsByPlayer($sysPlayerId));
    }
}
