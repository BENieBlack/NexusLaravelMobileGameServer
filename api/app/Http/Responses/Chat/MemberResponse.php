<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoomMember;

class MemberResponse extends _BaseResponse
{
    public function __construct(
        private readonly ChatRoomMember $member,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['member' => RoomPresenter::memberToArray($this->member)];
    }
}
