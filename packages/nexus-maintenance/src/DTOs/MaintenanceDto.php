<?php

namespace NexusMaintenance\DTOs;

use NexusUtilities\ClockUtility;
use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * メンテナンス情報のDTO
 * 
 * メンテナンス状態の情報を保持
 * 日時は全てY-m-d H:i:s形式の文字列で保持
 */
readonly class MaintenanceDto
{
    use JsonSerializableTrait;
    public function __construct(
        public bool $isMaintenance,
        public ?string $startAt = null,      // Y-m-d H:i:s形式
        public ?string $endAt = null,        // Y-m-d H:i:s形式
        public ?string $title = null,
        public ?string $message = null,
        public ?string $updatedAt = null,    // Y-m-d H:i:s形式
    ) {}

    /**
     * 現在がメンテナンス期間中かどうか
     */
    public function isCurrentlyUnderMaintenance(): bool
    {
        if (!$this->isMaintenance) {
            return false;
        }

        // start_atが設定されている場合、まだ開始時刻に達していなければfalse
        if ($this->startAt !== null && !ClockUtility::greaterThanOrEqual($this->startAt)) {
            return false;
        }

        // end_atが設定されている場合、終了時刻を過ぎていればfalse
        if ($this->endAt !== null && !ClockUtility::lessThanOrEqual($this->endAt)) {
            return false;
        }

        return true;
    }

    /**
     * 配列に変換
     * 
     * @return array{is_maintenance: bool, start_at: string|null, end_at: string|null, title: string|null, message: string|null, updated_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'is_maintenance' => $this->isMaintenance,
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
            'title' => $this->title,
            'message' => $this->message,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * 配列からインスタンスを作成
     * 
     * @param array{is_maintenance?: bool, start_at?: string|null, end_at?: string|null, title?: string|null, message?: string|null, updated_at?: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isMaintenance: $data['is_maintenance'] ?? false,
            startAt: $data['start_at'] ?? null,
            endAt: $data['end_at'] ?? null,
            title: $data['title'] ?? null,
            message: $data['message'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}
