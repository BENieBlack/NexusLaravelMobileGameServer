<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\SendMessageResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Domain\Chat\Support\ChatPlayerNameResolver;
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

    public function handle(int $sysPlayerId, int $chatRoomId, string $body): SendMessageResponse
    {
        $senderName = $this->nameResolver->resolve($sysPlayerId);

        $chatMessage = $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => $this->chatService->sendMessage($chatRoomId, $sysPlayerId, $senderName, $body)
            )
        );

        return SendMessageResponse::from($chatMessage);
    }
}
