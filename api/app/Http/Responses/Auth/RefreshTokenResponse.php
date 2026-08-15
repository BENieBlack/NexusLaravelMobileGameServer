<?php

namespace App\Http\Responses\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NexusAuth\ValueObjects\Token;

/**
 * RefreshTokenResponse
 *
 * トークンリフレッシュAPIのレスポンス
 * dto_token のみを返す（プレイヤー情報は含まない）
 */
class RefreshTokenResponse implements Responsable
{
    /**
     * @param  Token  $token  トークン情報DTO
     */
    public function __construct(
        public readonly Token $token,
    ) {}

    /**
     * レスポンスを生成
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'dto_token' => $this->token->toArray(),
        ]);
    }
}
