<?php

namespace NexusGacha\ValueObjects;

/**
 * ガチャ景品 Value Object
 *
 * ガチャで獲得した景品1件を表す不変オブジェクト。
 * 景品そのものに識別子は持たず、内容が同じなら同じ景品として扱う。
 */
final class GachaPrize
{
    /**
     * @param  string  $contentType  景品タイプ（item, unit, equipment等）
     * @param  string  $contentId  景品ID
     * @param  int  $amount  獲得数
     * @param  int  $rarity  レアリティ
     * @param  bool  $isGuaranteed  確定枠で獲得したか
     *
     * @throws \InvalidArgumentException 値が不正な場合
     */
    public function __construct(
        private readonly string $contentType,
        private readonly string $contentId,
        private readonly int $amount,
        private readonly int $rarity,
        private readonly bool $isGuaranteed,
    ) {
        if ($contentType === '') {
            throw new \InvalidArgumentException('景品タイプは必須です');
        }

        if ($contentId === '') {
            throw new \InvalidArgumentException('景品IDは必須です');
        }

        if ($amount < 1) {
            throw new \InvalidArgumentException("獲得数は1以上である必要があります: {$amount}");
        }

        if ($rarity < 0) {
            throw new \InvalidArgumentException("レアリティは0以上である必要があります: {$rarity}");
        }
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentId(): string
    {
        return $this->contentId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getRarity(): int
    {
        return $this->rarity;
    }

    public function isGuaranteed(): bool
    {
        return $this->isGuaranteed;
    }

    /**
     * 指定レアリティ以上か
     */
    public function isAtLeastRarity(int $rarity): bool
    {
        return $this->rarity >= $rarity;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->contentType === $other->contentType
            && $this->contentId === $other->contentId
            && $this->amount === $other->amount
            && $this->rarity === $other->rarity
            && $this->isGuaranteed === $other->isGuaranteed;
    }

    /**
     * 配列形式に変換
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'amount' => $this->amount,
            'rarity' => $this->rarity,
            'is_guaranteed' => $this->isGuaranteed,
        ];
    }
}
