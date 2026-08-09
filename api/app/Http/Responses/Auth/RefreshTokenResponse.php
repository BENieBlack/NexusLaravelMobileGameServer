<?php

namespace App\Http\Responses\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NexusAuth\DTOs\TokenDto;

/**
 * RefreshTokenResponse
 *
 * トークンリフレッシュAPIのレスポンス
 * dto_token のみを返す（プレイヤー情報は含まない）
 */
class RefreshTokenResponse implements Responsable
{
    /**
     * @param  Token  $tokenDto  トークン情報DTO
     */
    public function __construct(
        public readonly TokenDto $tokenDto,
    ) {}

    /**
     * レスポンスを生成
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'dto_token' => $this->tokenDto->toArray(),
        ]);
    }
}
