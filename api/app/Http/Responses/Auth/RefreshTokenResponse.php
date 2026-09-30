<?php

namespace App\Http\Responses\Auth;

use App\Http\Responses\_BaseResponse;
use NexusAuth\ValueObjects\Token;

/**
 * RefreshTokenResponse
 *
 * トークンリフレッシュAPIのレスポンス
 * token のみを返す（プレイヤー情報は含まない）
 */
class RefreshTokenResponse extends _BaseResponse
{
    /**
     * @param  Token  $token  トークン情報DTO
     */
    public function __construct(
        public readonly Token $token,
    ) {}

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token->toArray(),
        ];
    }
}
