<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoomMember;

/**
 * InviteResponse
 *
 * グループチャットへの招待APIのレスポンス
 */
class InviteResponse extends _BaseResponse
{
    public function __construct(
        private readonly ChatRoomMember $member,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function fromResult(ChatRoomMember $chatRoomMember): self
    {
        return new self($chatRoomMember);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['member' => RoomPresenter::memberToArray($this->member)];
    }
}
