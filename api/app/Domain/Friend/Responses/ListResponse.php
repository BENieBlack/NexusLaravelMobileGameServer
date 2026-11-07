<?php

namespace App\Domain\Friend\Responses;

use App\Domain\_BaseResponse;
use App\Models\Sys\SysFriendApply;
use Nexus\Core\Support\CustomCollection;

/**
 * ListResponse
 *
 * フレンドリストAPIのレスポンス
 * status=Acceptedのフレンドのみを返す
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $friends
     */
    public function __construct(
        public readonly array $friends,
    ) {}

    /**
     * SysFriendApplyのCollectionからレスポンスを生成
     *
     * 相手プレイヤーのmy_idのみを返す（自分がsenderかreceiverかに応じて判定）
     *
     * @param  CustomCollection<int, SysFriendApply>  $sysFriendApplyCollection
     * @param  int  $sysPlayerId  現在のプレイヤーのsys_player_id
     */
    public static function from(CustomCollection $sysFriendApplyCollection, int $sysPlayerId): self
    {
        $friendArray = $sysFriendApplyCollection->map(function (SysFriendApply $sysFriendApply) use ($sysPlayerId) {
            // 自分がsenderの場合はreceiverのmy_idを、receiverの場合はsenderのmy_idを返す
            $friendPlayer = $sysFriendApply->getSenderSysPlayerId() === $sysPlayerId
                ? $sysFriendApply->receivePlayer
                : $sysFriendApply->sendPlayer;

            return [
                'my_id' => $friendPlayer?->getMyId(),
                'name' => $friendPlayer?->getName(),
                'level' => $friendPlayer?->getLevel(),
                'created_at' => $sysFriendApply->getCreatedAt(),
            ];
        })->toArray();

        return new self(friends: $friendArray);
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'friend_list' => $this->friends,
        ];
    }
}
