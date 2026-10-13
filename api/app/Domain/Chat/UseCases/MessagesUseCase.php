<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use NexusChat\DataTransferObjects\ChatMessage;
use NexusChat\Services\ChatService;

/**
 * MessagesUseCase
 *
 * メッセージ履歴の取得
 */
class MessagesUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    /**
     * @return array<ChatMessage>
     */
    public function exec(int $sysPlayerId, int $chatRoomId, int $limit, ?int $cursor): array
    {
        return ChatExceptionTranslator::translate(
            fn () => $this->chatService->getMessages($chatRoomId, $sysPlayerId, $limit, $cursor)
        );
    }
}
