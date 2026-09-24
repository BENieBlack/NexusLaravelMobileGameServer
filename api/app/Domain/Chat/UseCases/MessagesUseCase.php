<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Http\Responses\Chat\MessageListResponse;
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

    public function exec(int $sysPlayerId, int $chatRoomId, int $limit, ?int $cursor): MessageListResponse
    {
        return ChatExceptionTranslator::translate(
            fn () => new MessageListResponse(
                $this->chatService->getMessages($chatRoomId, $sysPlayerId, $limit, $cursor)
            )
        );
    }
}
