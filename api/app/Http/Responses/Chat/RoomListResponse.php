<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoom;

class RoomListResponse extends _BaseResponse
{
    /**
     * @param  array<ChatRoom>  $rooms
     */
    public function __construct(
        private readonly array $rooms,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rooms' => array_map(
                fn (ChatRoom $room): array => RoomPresenter::toArray($room),
                $this->rooms
            ),
        ];
    }
}
