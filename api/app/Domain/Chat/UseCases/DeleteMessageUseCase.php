<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Http\Responses\Chat\ResultResponse;
use NexusChat\Services\ChatService;

/**
 * DeleteMessageUseCase
 *
 * 自分のメッセージを削除する（論理削除）
 */
class DeleteMessageUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function exec(int $sysPlayerId, int $chatMessageId): ResultResponse
    {
        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(function () use ($sysPlayerId, $chatMessageId) {
                $this->chatService->deleteMessage($chatMessageId, $sysPlayerId);

                return new ResultResponse;
            })
        );
    }
}
