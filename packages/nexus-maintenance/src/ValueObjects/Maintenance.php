<?php

namespace NexusMaintenance\ValueObjects;

use Nexus\Core\Traits\JsonSerializableTrait;
use Nexus\Core\Utilities\ClockUtility;

/**
 * メンテナンス情報 Value Object
 *
 * メンテナンス状態を保持する不変オブジェクト
 * 日時は全てY-m-d H:i:s形式の文字列で保持する
 */
final class Maintenance
{
    use JsonSerializableTrait;

    /**
     * @param  bool  $isMaintenance  メンテナンスフラグ
     * @param  string|null  $startAt  開始日時 (Y-m-d H:i:s)
     * @param  string|null  $endAt  終了日時 (Y-m-d H:i:s)
     * @param  string|null  $title  タイトル
     * @param  string|null  $message  メッセージ
     * @param  string|null  $updatedAt  更新日時 (Y-m-d H:i:s)
     *
     * @throws \InvalidArgumentException 開始日時が終了日時より後の場合
     */
    public function __construct(
        private readonly bool $isMaintenance,
        private readonly ?string $startAt = null,
        private readonly ?string $endAt = null,
        private readonly ?string $title = null,
        private readonly ?string $message = null,
        private readonly ?string $updatedAt = null,
    ) {
        if ($startAt !== null && $endAt !== null && $startAt > $endAt) {
            throw new \InvalidArgumentException(
                "メンテナンス終了日時は開始日時以降である必要があります: {$startAt} > {$endAt}"
            );
        }
    }

    /**
     * メンテナンスなしのインスタンスを生成
     */
    public static function none(): self
    {
        return new self(false);
    }

    /**
     * 現在がメンテナンス期間中かどうか
     */
    public function isCurrentlyUnderMaintenance(): bool
    {
        if (! $this->isMaintenance) {
            return false;
        }

        // start_atが設定されている場合、まだ開始時刻に達していなければfalse
        if ($this->startAt !== null && ! ClockUtility::greaterThanOrEqual($this->startAt)) {
            return false;
        }

        // end_atが設定されている場合、終了時刻を過ぎていればfalse
        if ($this->endAt !== null && ! ClockUtility::lessThanOrEqual($this->endAt)) {
            return false;
        }

        return true;
    }

    /**
     * メンテナンス中かどうか取得
     */
    public function getIsMaintenance(): bool
    {
        return $this->isMaintenance;
    }

    /**
     * 開始日時取得
     */
    public function getStartAt(): ?string
    {
        return $this->startAt;
    }

    /**
     * 終了日時取得
     */
    public function getEndAt(): ?string
    {
        return $this->endAt;
    }

    /**
     * タイトル取得
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * メッセージ取得
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * 更新日時取得
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->isMaintenance === $other->isMaintenance
            && $this->startAt === $other->startAt
            && $this->endAt === $other->endAt
            && $this->title === $other->title
            && $this->message === $other->message
            && $this->updatedAt === $other->updatedAt;
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
     * @param  array{is_maintenance?: bool, start_at?: string|null, end_at?: string|null, title?: string|null, message?: string|null, updated_at?: string|null}  $data
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
