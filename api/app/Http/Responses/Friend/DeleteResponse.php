<?php

namespace App\Http\Responses\Friend;

use App\Http\Responses\_BaseResponse;

/**
 * DeleteResponse
 *
 * フレンド削除APIのレスポンス
 */
class DeleteResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $deletedMyId,
        public readonly string $message,
    ) {}

    /**
     * 成功レスポンスを生成
     *
     * @param  string  $deletedMyId  削除されたフレンドのmy_id
     */
    public static function success(string $deletedMyId): self
    {
        return new self(
            success: true,
            deletedMyId: $deletedMyId,
            message: 'Friend deleted successfully',
        );
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'deleted_my_id' => $this->deletedMyId,
            'message' => $this->message,
        ];
    }
}
