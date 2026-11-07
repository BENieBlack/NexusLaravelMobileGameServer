<?php

namespace NexusEvent\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Contracts\EventMasterRepositoryInterface;
use NexusEvent\DataTransferObjects\Event;
use NexusEvent\ValueObjects\ContentPeriod;

/**
 * EventPeriodResolver
 *
 * イベントに入っているコンテンツの期間を、イベントの期間で上書きして返す。
 * コンテンツは自分の start_at / end_at を持っていても、イベントに入っていればイベントの期間が使われる。
 *
 * 同じコンテンツを複数のイベントで使い回してよい。イベントの期間は重ならないので、現在時刻に対して、
 * 採用するイベントは次の順で1つに決まる。
 *
 * 1. 開催中または交換期間中のイベント
 * 2. 無ければ、次に始まるイベント（start_at が最も近い未来）
 * 3. 無ければ、直近に終わったイベント
 *
 * イベントの数は少ないので、マスターはリクエストごとに1回だけ読み、メモリに持つ。
 */
class EventPeriodResolver
{
    /** @var array<string, list<Event>>|null "content_type\0content_mst_id" => 開始日時の昇順のイベント */
    private ?array $eventsByContent = null;

    public function __construct(
        private readonly EventMasterRepositoryInterface $masterRepository,
    ) {}

    /**
     * コンテンツの期間（イベントに入っていなければ null。呼び出し側は自身の期間を使う）
     *
     * @param  string|null  $now  Y-m-d H:i:s形式。省略時は現在時刻
     */
    public function resolve(string $contentType, string $contentMstId, ?string $now = null): ?ContentPeriod
    {
        $event = $this->resolveEvent($contentType, $contentMstId, $now);

        if ($event === null) {
            return null;
        }

        return new ContentPeriod(
            mstEventId: $event->getId(),
            startAt: $event->getStartAt(),
            endAt: EventContentType::usesExchangeEndAsEndAt($contentType)
                ? $event->getExchangeEndAt()
                : $event->getEndAt(),
            eventEndAt: $event->getEndAt(),
            exchangeEndAt: $event->getExchangeEndAt(),
        );
    }

    /**
     * 採用するイベント（コンテンツがどのイベントにも入っていなければ null）
     *
     * @param  string|null  $now  Y-m-d H:i:s形式。省略時は現在時刻
     */
    public function resolveEvent(string $contentType, string $contentMstId, ?string $now = null): ?Event
    {
        $candidates = $this->indexByContent()[$this->key($contentType, $contentMstId)] ?? [];

        if ($candidates === []) {
            return null;
        }

        $now ??= ClockUtility::nowToString();

        // 1. 開催中または交換期間中
        foreach ($candidates as $event) {
            if ($event->isOpenAt($now)) {
                return $event;
            }
        }

        // 2. 次に始まるイベント（開始日時の昇順なので、最初に見つかったものが最も近い）
        foreach ($candidates as $event) {
            if ($event->getStartAt() > $now) {
                return $event;
            }
        }

        // 3. 全て終わっている。直近に終わったもの（開始日時の昇順の最後）
        return $candidates[array_key_last($candidates)];
    }

    /**
     * @return array<string, list<Event>>
     */
    private function indexByContent(): array
    {
        if ($this->eventsByContent !== null) {
            return $this->eventsByContent;
        }

        $events = [];
        foreach ($this->masterRepository->selectAllEvents() as $event) {
            $events[$event->getId()] = $event;
        }

        $index = [];
        foreach ($this->masterRepository->selectAllContents() as $content) {
            $event = $events[$content->getMstEventId()] ?? null;

            // マスターに無いイベントを指す行は、取り込み時の検査で弾く。ここでは無視する
            if ($event === null) {
                continue;
            }

            $index[$this->key($content->getContentType(), $content->getContentMstId())][] = $event;
        }

        foreach ($index as &$list) {
            usort($list, fn (Event $a, Event $b) => $a->getStartAt() <=> $b->getStartAt());
        }
        unset($list);

        return $this->eventsByContent = $index;
    }

    private function key(string $contentType, string $contentMstId): string
    {
        return $contentType."\0".$contentMstId;
    }
}
