<?php

namespace App\Domain\Auth\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class DtoMaintenance implements Arrayable, JsonSerializable
{
    /**
     * @param string $title メンテナンスタイトル
     * @param string $message メンテナンスメッセージ
     * @param string $startAt メンテナンス開始日時
     * @param string|null $endAt メンテナンス終了日時
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $startAt,
        public ?string $endAt = null,
    ) {
    }

    /**
     * 配列に変換
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
        ];
    }

    /**
     * JSON シリアライズ
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
