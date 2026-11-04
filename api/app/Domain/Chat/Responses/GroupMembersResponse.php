<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoomMember;

/**
 * GroupMembersResponse
 *
 * グループチャットのメンバー一覧APIのレスポンス
 */
class GroupMembersResponse extends _BaseResponse
{
    /**
     * @param  array<ChatRoomMember>  $members
     */
    public function __construct(
        private readonly array $members,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     *
     * @param  array<ChatRoomMember>  $chatRoomMembers
     */
    public static function fromResult(array $chatRoomMembers): self
    {
        return new self($chatRoomMembers);
    }

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
