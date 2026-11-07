<?php

namespace NexusEvent\ValueObjects;

/**
 * ContentPeriod
 *
 * イベントに入っているコンテンツの、イベントの期間で上書きした期間
 */
class ContentPeriod
{
    /**
     * @param  string  $startAt  コンテンツの start_at として使う日時
     * @param  string  $endAt  コンテンツの end_at として使う日時（種別によって、イベントの end_at か exchange_end_at）
     * @param  string  $eventEndAt  イベントの end_at（プレイ期間の終わり）
     * @param  string  $exchangeEndAt  イベントの exchange_end_at（交換・受け取りの期間の終わり）
     */
    public function __construct(
        private readonly string $mstEventId,
        private readonly string $startAt,
        private readonly string $endAt,
        private readonly string $eventEndAt,
        private readonly string $exchangeEndAt,
    ) {}

    public function getMstEventId(): string
    {
        return $this->mstEventId;
    }

    public function getStartAt(): string
    {
        return $this->startAt;
    }

    public function getEndAt(): string
    {
        return $this->endAt;
    }

    public function getEventEndAt(): string
    {
        return $this->eventEndAt;
    }

    public function getExchangeEndAt(): string
    {
        return $this->exchangeEndAt;
    }
}
