<?php

namespace App\Domain;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * _BaseResponse
 *
 * UseCase が返すレスポンスの基底クラス
 *
 * UseCase の handle() はアクションごとの Response を返し、Controller はそれを返すだけにする。
 * ここでは API の形（snake_case のキーの配列）への整形だけを持ち、HTTP には依存しない。
 * JSON レスポンスへの変換は App\Http\Controllers\_BaseController::execute() が行う。
 *
 * @implements Arrayable<string, mixed>
 */
abstract class _BaseResponse implements Arrayable, JsonSerializable
{
    /**
     * レスポンスの配列（キーは snake_case）
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
