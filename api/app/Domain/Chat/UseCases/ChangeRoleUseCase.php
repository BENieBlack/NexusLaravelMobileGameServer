<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Services\ChatService;

/**
 * ChangeRoleUseCase
 *
 * グループチャットのロールを変更する（OWNERのみ）
 */
class ChangeRoleUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function exec(int $sysPlayerId, int $chatRoomId, int $targetSysPlayerId, ChatRoomRole $role): void
    {
        $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(function () use ($sysPlayerId, $chatRoomId, $targetSysPlayerId, $role) {
                $this->chatService->changeRole($chatRoomId, $sysPlayerId, $targetSysPlayerId, $role);
            })
        );
    }
}
