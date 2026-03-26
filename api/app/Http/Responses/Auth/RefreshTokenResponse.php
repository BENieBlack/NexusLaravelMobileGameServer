<?php

namespace App\Http\Responses\Auth;

use App\Domain\Auth\DTOs\DtoToken;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * RefreshTokenResponse
 * 
 * トークンリフレッシュAPIのレスポンス
 * dto_token のみを返す（プレイヤー情報は含まない）
 */
class RefreshTokenResponse implements Responsable
{
    /**
     * @param DtoToken $dtoToken トークン情報DTO
     */
    public function __construct(
        public readonly DtoToken $dtoToken,
    ) {
    }

    /**
     * レスポンスを生成
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'dto_token' => $this->dtoToken->toArray(),
        ]);
    }
}
