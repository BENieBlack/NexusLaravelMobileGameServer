<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * _BaseResponseInterface
 * 
 * すべてのレスポンスクラスが実装すべきインターフェース
 */
interface _BaseResponseInterface
{
    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * JSON シリアライズ
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed;

    /**
     * JsonResponseに変換
     *
     * @param int $status HTTPステータスコード
     * @return JsonResponse
     */
    public function toJsonResponse(int $status = 200): JsonResponse;
}
