<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * RoomsResponse
 *
 * 参加中のルーム一覧APIのレスポンス
 */
class RoomsResponse extends _BaseResponse
{
    /**
     * @param  array<ChatRoom>  $rooms
     */
    public function __construct(
        private readonly array $rooms,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     *
     * @param  array<ChatRoom>  $chatRooms
     */
    public static function fromResult(array $chatRooms): self
    {
        return new self($chatRooms);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chat_room_list' => array_map(
                fn (ChatRoom $room): array => RoomPresenter::toArray($room),
                $this->rooms
            ),
        ];
    }
}
