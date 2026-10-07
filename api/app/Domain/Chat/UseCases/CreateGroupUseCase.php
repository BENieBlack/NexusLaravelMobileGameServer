<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
use NexusChat\DataTransferObjects\ChatRoom;
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

    public function exec(int $sysPlayerId, string $name): ChatRoom
    {
        $ownerName = $this->nameResolver->resolve($sysPlayerId);

        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => $this->chatService->createGroupRoom($name, $sysPlayerId, $ownerName)
            )
        );
    }
}
