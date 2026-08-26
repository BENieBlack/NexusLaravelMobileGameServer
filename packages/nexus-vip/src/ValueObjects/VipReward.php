<?php

namespace NexusVip\ValueObjects;

/**
 * VIPレベルアップ報酬 Value Object
 *
 * 報酬1件分の内容を保持する不変オブジェクト。
 * 実際の配布量は content_quantity × amount で算出する。
 */
final class VipReward
{
    /**
     * @param  string  $contentType  報酬タイプ（diamond, item, unit等）
     * @param  string  $contentId  報酬ID
     * @param  array<string, mixed>|null  $contentOption  報酬オプション
     * @param  int  $contentQuantity  報酬の基本個数
     * @param  int  $amount  報酬の倍率
     * @param  bool  $isPaid  有償フラグ
     *
     * @throws \InvalidArgumentException 値が不正な場合
     */
    public function __construct(
        private readonly string $contentType,
        private readonly string $contentId,
        private readonly ?array $contentOption,
        private readonly int $contentQuantity,
        private readonly int $amount,
        private readonly bool $isPaid = false,
    ) {
        if ($contentType === '') {
            throw new \InvalidArgumentException('報酬タイプは必須です');
        }

        if ($contentId === '') {
            throw new \InvalidArgumentException('報酬IDは必須です');
        }

        if ($contentQuantity < 0) {
            throw new \InvalidArgumentException("報酬の基本個数は0以上である必要があります: {$contentQuantity}");
        }

        if ($amount < 0) {
            throw new \InvalidArgumentException("報酬の倍率は0以上である必要があります: {$amount}");
        }
    }

    /**
     * コンテンツタイプを取得
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * コンテンツIDを取得
     */
    public function getContentId(): string
    {
        return $this->contentId;
    }

    /**
     * コンテンツオプションを取得
     *
     * @return array<string, mixed>|null
     */
    public function getContentOption(): ?array
    {
        return $this->contentOption;
    }

    /**
     * コンテンツ数量を取得
     */
    public function getContentQuantity(): int
    {
        return $this->contentQuantity;
    }

    /**
     * 量を取得
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * 有償かどうかを取得
     */
    public function getIsPaid(): bool
    {
        return $this->isPaid;
    }

    /**
     * 実際の配布総量を取得（content_quantity × amount）
     */
    public function getTotalQuantity(): int
    {
        return $this->contentQuantity * $this->amount;
    }

    /**
     * 配布対象が無いか（総量0）
     */
    public function isEmpty(): bool
    {
        return $this->getTotalQuantity() === 0;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->contentType === $other->contentType
            && $this->contentId === $other->contentId
            && $this->contentOption === $other->contentOption
            && $this->contentQuantity === $other->contentQuantity
            && $this->amount === $other->amount
            && $this->isPaid === $other->isPaid;
    }

    /**
     * 配列に変換
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'content_option' => $this->contentOption,
            'content_quantity' => $this->contentQuantity,
            'amount' => $this->amount,
            'total_quantity' => $this->getTotalQuantity(),
            'is_paid' => $this->isPaid,
        ];
    }
}
