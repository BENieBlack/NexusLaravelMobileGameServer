<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysFriendApply;
use Illuminate\Database\Eloquent\Collection;

/**
 * ApplyListResponse
 * 
 * フレンド申請リストAPIのレスポンス
 */
class ApplyListResponse extends _BaseResponse
{
    /**
     * @param array<int, array<string, mixed>> $applies
     */
    public function __construct(
        public readonly array $applies,
    ) {
    }

    /**
     * SysFriendApplyのCollectionからレスポンスを生成
     *
     * @param Collection<int, SysFriendApply> $sysFriendApplyCollection
     * @return self
     */
    public static function fromCollection(Collection $sysFriendApplyCollection): self
    {
        $applyArray = $sysFriendApplyCollection->map(function (SysFriendApply $sysFriendApply) {
            return [
                'sys_friend_apply_id' => $sysFriendApply->id,
                'sender_my_id' => $sysFriendApply->sendPlayer->my_id,
                'receiver_my_id' => $sysFriendApply->receivePlayer->my_id,
                'status' => $sysFriendApply->status,
                'created_at' => $sysFriendApply->created_at->toDateTimeString(),
                'updated_at' => $sysFriendApply->updated_at->toDateTimeString(),
            ];
        })->toArray();

        return new self(applies: $applyArray);
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applies' => $this->applies,
        ];
    }
}
