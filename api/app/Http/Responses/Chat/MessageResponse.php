<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatMessage;

class MessageResponse extends _BaseResponse
{
    public function __construct(
        private readonly ChatMessage $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['message' => RoomPresenter::messageToArray($this->message)];
    }
}
