<?php

namespace App\Http\Responses\Player;

use App\Http\Responses\_BaseResponse;

/**
 * MeResponse
 * 
 * 認証済みプレイヤー情報レスポンス
 * クライアントに必要最小限の情報のみを返す
 */
class MeResponse extends _BaseResponse
{
    public function __construct(
        public readonly string $myId,
        public readonly ?string $name,
    ) {}

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'my_id' => $this->myId,
            'name' => $this->name,
        ];
    }
}
