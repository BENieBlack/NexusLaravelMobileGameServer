<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatMessage;

class MessageListResponse extends _BaseResponse
{
    /**
     * @param  array<ChatMessage>  $messages
     */
    public function __construct(
        private readonly array $messages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'messages' => array_map(
                fn (ChatMessage $message): array => RoomPresenter::messageToArray($message),
                $this->messages
            ),
        ];
    }
}
