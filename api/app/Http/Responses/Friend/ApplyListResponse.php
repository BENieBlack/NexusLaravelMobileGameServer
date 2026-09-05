<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysFriendApply;
use Illuminate\Database\Eloquent\Collection;
use NexusFriend\DataTransferObjects\FriendApply;

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
     * @param  Collection<int, SysFriendApply>  $sysFriendApplyCollection
     */
    public static function fromCollection(Collection $sysFriendApplyCollection): self
    {
        $applyArray = $sysFriendApplyCollection->map(function (SysFriendApply $sysFriendApply) {
            return [
                'sys_friend_apply_id' => $sysFriendApply->id,
                'sender_my_id' => $sysFriendApply->sendPlayer->my_id,
                'receiver_my_id' => $sysFriendApply->receivePlayer->my_id,
                'status' => $sysFriendApply->status,
                'created_at' => $sysFriendApply->getCreatedAt(),
                'updated_at' => $sysFriendApply->getUpdatedAt(),
            ];
        })->toArray();

        return new self(applies: $applyArray);
    }

    /**
     * FriendApply配列からレスポンスを生成
     *
     * 注意: この実装は暫定的なもので、現在はModelのリレーション情報に依存しています
     * 将来的にはDTOに必要な情報を含めるべきです
     *
     * @param  array<FriendApply>  $dtos
     */
    public static function fromDtoArray(array $dtos): self
    {
        $applyArray = [];
        foreach ($dtos as $dto) {
            // DTOからModelを取得してリレーション情報を使用（暫定対応）
            $model = SysFriendApply::with(['sendPlayer', 'receivePlayer'])->find($dto->getId());

            if ($model) {
                $applyArray[] = [
                    'sys_friend_apply_id' => $dto->getId(),
                    'sender_my_id' => $model->sendPlayer->my_id,
                    'receiver_my_id' => $model->receivePlayer->my_id,
                    'status' => $dto->getStatus(),
                    'created_at' => $dto->getCreatedAt(),
                    'updated_at' => $dto->getUpdatedAt(),
                ];
            }
        }

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
