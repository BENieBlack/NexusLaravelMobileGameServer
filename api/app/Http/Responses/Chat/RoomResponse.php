<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * RoomResponse
 *
 * チャットルーム1件を返すレスポンスの共通部分。channel_name はクライアントの購読先
 *
 * フレンドDM・ギルド・グループ作成で同じ形を返す。
 * Controller からは各アクションの子クラス（FriendRoomResponse など）を使う
 */
abstract class RoomResponse extends _BaseResponse
{
    final public function __construct(
        private readonly ChatRoom $room,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function fromResult(ChatRoom $chatRoom): static
    {
        return new static($chatRoom);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['room' => RoomPresenter::toArray($this->room)];
    }
}
