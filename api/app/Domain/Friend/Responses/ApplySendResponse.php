<?php

namespace App\Domain\Friend\Responses;

use App\Domain\_BaseResponse;
use NexusFriend\DataTransferObjects\FriendApply;

/**
 * ApplySendResponse
 *
 * フレンド申請送信APIのレスポンス
 */
class ApplySendResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $sysFriendApplyId,
        public readonly int $senderSysPlayerId,
        public readonly int $receiverSysPlayerId,
        public readonly string $status,
        public readonly string $createdAt,
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
        ];
    }
}
