<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysFriendApply;

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
     * SysFriendApplyモデルからレスポンスを生成
     */
    public static function fromModel(SysFriendApply $sysFriendApply): self
    {
        return new self(
            sysFriendApplyId: $sysFriendApply->id,
            senderSysPlayerId: $sysFriendApply->sender_sys_player_id,
            receiverSysPlayerId: $sysFriendApply->receiver_sys_player_id,
            status: $sysFriendApply->status,
            createdAt: $sysFriendApply->getCreatedAt(),
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
