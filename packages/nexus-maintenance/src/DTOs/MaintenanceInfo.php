<?php

namespace LaravelMobileGame\DTOs;

use Carbon\CarbonImmutable;

/**
 * メンテナンス情報DTO
 * 
 * メンテナンス状態の情報を保持
 */
readonly class MaintenanceInfo
{
    public function __construct(
        public bool $isMaintenance,
        public ?CarbonImmutable $startAt = null,
        public ?CarbonImmutable $endAt = null,
        public ?string $title = null,
        public ?string $message = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * 現在がメンテナンス期間中かどうか
     */
    public function isCurrentlyUnderMaintenance(): bool
    {
        if (!$this->isMaintenance) {
            return false;
        }

        $now = CarbonImmutable::now();

        // start_atが設定されている場合、まだ開始時刻に達していなければfalse
        if ($this->startAt !== null && $now->isBefore($this->startAt)) {
            return false;
        }

        // end_atが設定されている場合、終了時刻を過ぎていればfalse
        if ($this->endAt !== null && $now->isAfter($this->endAt)) {
            return false;
        }

        return true;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'is_maintenance' => $this->isMaintenance,
            'start_at' => $this->startAt?->toIso8601String(),
            'end_at' => $this->endAt?->toIso8601String(),
            'title' => $this->title,
            'message' => $this->message,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    /**
     * JSON文字列に変換
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 配列からインスタンスを作成
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isMaintenance: $data['is_maintenance'] ?? false,
            startAt: isset($data['start_at']) ? CarbonImmutable::parse($data['start_at']) : null,
            endAt: isset($data['end_at']) ? CarbonImmutable::parse($data['end_at']) : null,
            title: $data['title'] ?? null,
            message: $data['message'] ?? null,
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
        );
    }
}
