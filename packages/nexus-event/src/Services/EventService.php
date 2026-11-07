<?php

namespace NexusEvent\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Contracts\EventMasterRepositoryInterface;
use NexusEvent\DataTransferObjects\Event;
use NexusEvent\DataTransferObjects\EventContent;

/**
 * EventService
 *
 * イベントの検索と、段階の判定
 *
 * イベントの期間は全て重ならない（マスター取り込み時に検査する）ので、
 * 開催中または交換期間中のイベントは、常に最大1つ。
 */
class EventService
{
    public function __construct(
        private readonly EventMasterRepositoryInterface $masterRepository,
    ) {}

    /**
     * 全イベント（開始日時の昇順）
     *
     * @return array<int, Event>
     */
    public function selectAllEvents(): array
    {
        $events = $this->masterRepository->selectAllEvents();
        usort($events, fn (Event $a, Event $b) => [$a->getStartAt(), $a->getSortOrder(), $a->getId()] <=> [$b->getStartAt(), $b->getSortOrder(), $b->getId()]);

        return $events;
    }

    public function selectEventById(string $mstEventId): ?Event
    {
        foreach ($this->masterRepository->selectAllEvents() as $event) {
            if ($event->getId() === $mstEventId) {
                return $event;
            }
        }

        return null;
    }

    /**
     * 開催中または交換期間中のイベント（無ければ null）
     *
     * @param  string|null  $now  Y-m-d H:i:s形式。省略時は現在時刻
     */
    public function findCurrentEvent(?string $now = null): ?Event
    {
        $now ??= ClockUtility::nowToString();

        foreach ($this->selectAllEvents() as $event) {
            if ($event->isOpenAt($now)) {
                return $event;
            }
        }

        return null;
    }

    /**
     * イベントに束ねられたコンテンツ（表示順）
     *
     * @return array<int, EventContent>
     */
    public function selectContentsByEventId(string $mstEventId): array
    {
        $contents = array_values(array_filter(
            $this->masterRepository->selectAllContents(),
            fn (EventContent $content) => $content->getMstEventId() === $mstEventId,
        ));
        usort($contents, fn (EventContent $a, EventContent $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $contents;
    }
}
