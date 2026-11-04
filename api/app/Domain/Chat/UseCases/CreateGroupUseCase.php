<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\CreateGroupResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
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

    public function handle(int $sysPlayerId, string $name): CreateGroupResponse
    {
        $ownerName = $this->nameResolver->resolve($sysPlayerId);

        $chatRoom = $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => $this->chatService->createGroupRoom($name, $sysPlayerId, $ownerName)
            )
        );

        return CreateGroupResponse::from($chatRoom);
    }
}
