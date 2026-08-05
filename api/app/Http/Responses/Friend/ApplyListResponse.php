<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysFriendApply;
use Illuminate\Database\Eloquent\Collection;
use NexusFriend\Dto\FriendApplyDto;

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
     * FriendApplyDto配列からレスポンスを生成
     * 
     * 注意: この実装は暫定的なもので、現在はModelのリレーション情報に依存しています
     * 将来的にはDTOに必要な情報を含めるべきです
     *
     * @param array<FriendApplyDto> $dtos
     * @return self
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
                    'created_at' => $dto->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $dto->getUpdatedAt()->format('Y-m-d H:i:s'),
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
