<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
use App\Http\Responses\Chat\MessageResponse;
use NexusChat\Services\ChatService;

/**
 * SendMessageUseCase
 *
 * メッセージ送信
 */
class SendMessageUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ChatPlayerNameResolver $nameResolver,
    ) {}

    public function exec(int $sysPlayerId, int $chatRoomId, string $body): MessageResponse
    {
        $senderName = $this->nameResolver->resolve($sysPlayerId);

        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => new MessageResponse(
                    $this->chatService->sendMessage($chatRoomId, $sysPlayerId, $senderName, $body)
                )
            )
        );
    }
}
