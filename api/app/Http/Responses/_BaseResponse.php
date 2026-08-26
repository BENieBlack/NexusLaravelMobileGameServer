<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use JsonSerializable;

/**
 * _BaseResponse
 *
 * すべてのレスポンスクラスの基底クラス
 * 共通のシリアライゼーション処理を提供
 *
 * @implements Arrayable<string, mixed>
 */
abstract class _BaseResponse implements _BaseResponseInterface, Arrayable, JsonSerializable
{
    /**
     * 配列に変換
     *
     * 各レスポンスクラスで必ずオーバーライドすること
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * JSON シリアライズ
     *
     * toArray()を呼び出してJSON形式に変換
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * JsonResponseに変換
     *
     * デフォルトではHTTPステータスコード200でレスポンスを返す
     * 必要に応じて各レスポンスクラスでオーバーライド可能
     *
     * @param  int  $status  HTTPステータスコード
     */
    public function toJsonResponse(int $status = 200): JsonResponse
    {
        return response()->json($this->toArray(), $status);
    }
}
