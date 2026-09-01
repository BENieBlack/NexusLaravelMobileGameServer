<?php

namespace NexusBilling\ValueObjects;

use Nexus\Core\Traits\JsonSerializableTrait;

/**
 * サブスクリプション状態 Value Object
 *
 * サブスクリプション商品の現在の状態を保持する不変オブジェクト。
 *
 * 元のDTOは autoRenew / state / cancelledAt が public な可変プロパティで、
 * 生成後に書き換えられる状態だったため、すべて不変にした。
 */
final class Subscription
{
    use JsonSerializableTrait;

    /**
     * @param  bool  $isActive  サブスクリプションが有効か
     * @param  string  $expiresAt  有効期限 (Y-m-d H:i:s)
     * @param  bool  $autoRenew  自動更新が有効か
     * @param  string|null  $state  状態（active, expired, cancelled等）
     * @param  string|null  $cancelledAt  キャンセル日時 (Y-m-d H:i:s)
     */
    public function __construct(
        private readonly bool $isActive,
        private readonly string $expiresAt,
        private readonly bool $autoRenew,
        private readonly ?string $state = null,
        private readonly ?string $cancelledAt = null,
    ) {}

    /**
     * サブスクリプションが有効か
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * 有効期限取得
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    /**
     * 自動更新が有効か
     */
    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    /**
     * 状態取得
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * キャンセル日時取得
     */
    public function getCancelledAt(): ?string
    {
        return $this->cancelledAt;
    }

    /**
     * キャンセル済みか
     */
    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    /**
     * 指定時刻の時点で期限切れか
     *
     * @param  string  $now  判定する時刻 (Y-m-d H:i:s)
     */
    public function isExpiredAt(string $now): bool
    {
        return $this->expiresAt <= $now;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->isActive === $other->isActive
            && $this->expiresAt === $other->expiresAt
            && $this->autoRenew === $other->autoRenew
            && $this->state === $other->state
            && $this->cancelledAt === $other->cancelledAt;
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_active' => $this->isActive,
            'expires_at' => $this->expiresAt,
            'auto_renew' => $this->autoRenew,
            'state' => $this->state,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
