<?php

namespace App\Domain\Notification\Responses;

use App\Domain\_BaseResponse;

/**
 * ReadResponse
 *
 * 既読処理の結果。クライアントがバッジを描き直せるよう未読数を返す
 */
class ReadResponse extends _BaseResponse
{
    final public function __construct(
        private readonly int $unreadCount,
    ) {}

    /**
     * UseCaseの結果（既読にした後の未読数）からレスポンスを生成
     */
    public static function from(int $unreadCount): static
    {
        return new static($unreadCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unread_count' => $this->unreadCount,
        ];
    }
}
