<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;
use NexusFriend\DataTransferObjects\FriendApply;

/**
 * ApplyRejectResponse
 *
 * フレンド申請却下APIのレスポンス
 */
class ApplyRejectResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $sysFriendApplyId,
        public readonly int $senderSysPlayerId,
        public readonly int $receiverSysPlayerId,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * FriendApply DTOからレスポンスを生成
     */
    public static function fromResult(FriendApply $friendApply): self
    {
        return new self(
            sysFriendApplyId: $friendApply->getId(),
            senderSysPlayerId: $friendApply->getSenderPlayerId(),
            receiverSysPlayerId: $friendApply->getReceiverPlayerId(),
            status: $friendApply->getStatus(),
            createdAt: (string) $friendApply->getCreatedAt(),
            updatedAt: (string) $friendApply->getUpdatedAt(),
        );
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sys_friend_apply_id' => $this->sysFriendApplyId,
            'sender_sys_player_id' => $this->senderSysPlayerId,
            'receiver_sys_player_id' => $this->receiverSysPlayerId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
