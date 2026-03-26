<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysFriendApply;
use Illuminate\Database\Eloquent\Collection;

/**
 * ListResponse
 * 
 * フレンドリストAPIのレスポンス
 * status=Acceptedのフレンドのみを返す
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param array<int, array<string, mixed>> $friends
     */
    public function __construct(
        public readonly array $friends,
    ) {
    }

    /**
     * SysFriendApplyのCollectionからレスポンスを生成
     * 
     * 相手プレイヤーのmy_idのみを返す（自分がsenderかreceiverかに応じて判定）
     *
     * @param Collection<int, SysFriendApply> $sysFriendApplyCollection
     * @param int $sysPlayerId 現在のプレイヤーのsys_player_id
     * @return self
     */
    public static function fromCollection(Collection $sysFriendApplyCollection, int $sysPlayerId): self
    {
        $friendArray = $sysFriendApplyCollection->map(function (SysFriendApply $sysFriendApply) use ($sysPlayerId) {
            // 自分がsenderの場合はreceiverのmy_idを、receiverの場合はsenderのmy_idを返す
            $friendPlayer = $sysFriendApply->sender_sys_player_id === $sysPlayerId
                ? $sysFriendApply->receivePlayer
                : $sysFriendApply->sendPlayer;

            return [
                'my_id' => $friendPlayer->my_id,
                'name' => $friendPlayer->name,
                'level' => $friendPlayer->level,
                'created_at' => $sysFriendApply->created_at->toDateTimeString(),
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
            'friends' => $this->friends,
        ];
    }
}
