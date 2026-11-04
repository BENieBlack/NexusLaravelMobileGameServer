<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\RoomsResponse;
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

    public function handle(int $sysPlayerId): RoomsResponse
    {
        return RoomsResponse::fromResult($this->chatService->getRoomsByPlayer($sysPlayerId));
    }
}
