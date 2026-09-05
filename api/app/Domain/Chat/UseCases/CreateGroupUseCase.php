<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
use App\Http\Responses\Chat\RoomResponse;
use NexusChat\Services\ChatService;

/**
 * CreateGroupUseCase
 *
 * グループチャットを作成する（作成者はOWNERとして参加）
 */
class CreateGroupUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ChatPlayerNameResolver $nameResolver,
    ) {}

    public function exec(int $sysPlayerId, string $name): RoomResponse
    {
        $ownerName = $this->nameResolver->resolve($sysPlayerId);

        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => new RoomResponse($this->chatService->createGroupRoom($name, $sysPlayerId, $ownerName))
            )
        );
    }
}
