<?php

namespace App\Domain\Friend\Responses;

use App\Domain\_BaseResponse;

/**
 * DeleteResponse
 *
 * フレンド削除APIのレスポンス
 */
class DeleteResponse extends _BaseResponse
{
    public function __construct(
        public readonly string $deletedMyId,
        public readonly string $message,
    ) {}

    /**
     * 削除の結果からレスポンスを生成
     *
     * @param  string  $deletedMyId  削除されたフレンドのmy_id
     */
    public static function fromResult(string $deletedMyId): self
    {
        return new self(
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
            'deleted_my_id' => $this->deletedMyId,
            'message' => $this->message,
        ];
    }
}
