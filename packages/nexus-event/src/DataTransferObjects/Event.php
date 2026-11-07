<?php

namespace NexusEvent\DataTransferObjects;

use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventPhase;

/**
 * Event
 *
 * イベント（mst_event の1行）。期間の正本
 */
class Event
{
    /**
     * @param  string  $startAt  開始日時（Y-m-d H:i:s形式）
     * @param  string  $endAt  プレイ期間の終わり（Y-m-d H:i:s形式）
     * @param  string  $exchangeEndAt  交換・受け取りの期間の終わり（Y-m-d H:i:s形式。endAt と同じ値なら交換期間なし）
     */
    public function __construct(
        private readonly string $id,
        private readonly string $startAt,
        private readonly string $endAt,
        private readonly string $exchangeEndAt,
        private readonly int $sortOrder,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStartAt(): string
    {
        return $this->startAt;
    }

    public function getEndAt(): string
    {
        return $this->endAt;
    }

    public function getExchangeEndAt(): string
    {
        return $this->exchangeEndAt;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * 指定時刻の段階（省略時は現在時刻）
     *
     * 境界の時刻（start_at / end_at / exchange_end_at ちょうど）は、手前の段階に含める。
     *
     * @param  string|null  $now  Y-m-d H:i:s形式
     */
    public function phaseAt(?string $now = null): string
    {
        $now ??= ClockUtility::nowToString();

        return match (true) {
            $now < $this->startAt => EventPhase::BEFORE,
            $now <= $this->endAt => EventPhase::PLAYING,
            $now <= $this->exchangeEndAt => EventPhase::EXCHANGING,
            default => EventPhase::CLOSED,
        };
    }

    /**
     * 開催中または交換期間中か（start_at 以上 exchange_end_at 以下）
     *
     * @param  string|null  $now  Y-m-d H:i:s形式
     */
    public function isOpenAt(?string $now = null): bool
    {
        $phase = $this->phaseAt($now);

        return $phase === EventPhase::PLAYING || $phase === EventPhase::EXCHANGING;
    }
}
