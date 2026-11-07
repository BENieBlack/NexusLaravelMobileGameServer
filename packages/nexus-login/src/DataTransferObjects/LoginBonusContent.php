<?php

namespace NexusLogin\DataTransferObjects;

/**
 * LoginBonusContent
 *
 * ログインボーナスの報酬1件（mst_login_bonus_content などの1行）を表すDTO
 */
class LoginBonusContent
{
    /**
     * @param  array<string, mixed>|null  $contentOption
     */
    public function __construct(
        private readonly string $contentType,
        private readonly string $contentMstId,
        private readonly ?array $contentOption,
        private readonly int $contentQuantity,
        private readonly int $amount,
        private readonly bool $isPaid,
    ) {}

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContentOption(): ?array
    {
        return $this->contentOption;
    }

    public function getContentQuantity(): int
    {
        return $this->contentQuantity;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getIsPaid(): bool
    {
        return $this->isPaid;
    }

    /**
     * 実際に配布する数量（1配布あたりの数量 × 配布回数）
     */
    public function getTotalQuantity(): int
    {
        return $this->contentQuantity * $this->amount;
    }
}
