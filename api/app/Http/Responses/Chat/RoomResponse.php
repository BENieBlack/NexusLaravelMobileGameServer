<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * RoomResponse
 *
 * チャットルーム1件。channel_name はクライアントの購読先
 */
class RoomResponse extends _BaseResponse
{
    public function __construct(
        private readonly ChatRoom $room,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['room' => RoomPresenter::toArray($this->room)];
    }
}
