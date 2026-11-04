<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Responses\MessagesResponse;
use App\Domain\Chat\Support\ChatExceptionTranslator;
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

    public function handle(int $sysPlayerId, int $chatRoomId, int $limit, ?int $cursor): MessagesResponse
    {
        $chatMessages = ChatExceptionTranslator::translate(
            fn () => $this->chatService->getMessages($chatRoomId, $sysPlayerId, $limit, $cursor)
        );

        return MessagesResponse::from($chatMessages);
    }
}
