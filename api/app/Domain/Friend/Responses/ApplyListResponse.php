<?php

namespace App\Domain\Friend\Responses;

use App\Domain\_BaseResponse;
use App\Models\Sys\SysFriendApply;
use Nexus\Core\Support\CustomCollection;

/**
 * ApplyListResponse
 *
 * フレンド申請リストAPIのレスポンス
 */
class ApplyListResponse extends _BaseResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $applies
     */
    public function __construct(
        public readonly array $applies,
    ) {}

    /**
     * SysFriendApplyのCollectionからレスポンスを生成
     *
     * @param  CustomCollection<int, SysFriendApply>  $sysFriendApplyCollection
     */
    public static function from(CustomCollection $sysFriendApplyCollection): self
    {
        $applyArray = $sysFriendApplyCollection->map(function (SysFriendApply $sysFriendApply) {
            return [
                'sys_friend_apply_id' => $sysFriendApply->getId(),
                'sender_my_id' => $sysFriendApply->sendPlayer?->getMyId(),
                'receiver_my_id' => $sysFriendApply->receivePlayer?->getMyId(),
                'status' => $sysFriendApply->getStatus(),
                'created_at' => $sysFriendApply->getCreatedAt(),
                'updated_at' => $sysFriendApply->getUpdatedAt(),
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
            'friend_apply_list' => $this->applies,
        ];
    }
}
