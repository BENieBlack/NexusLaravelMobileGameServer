<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoomMember;

class MemberListResponse extends _BaseResponse
{
    /**
     * @param  array<ChatRoomMember>  $members
     */
    public function __construct(
        private readonly array $members,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chat_room_member_list' => array_map(
                fn (ChatRoomMember $member): array => RoomPresenter::memberToArray($member),
                $this->members
            ),
        ];
    }
}
