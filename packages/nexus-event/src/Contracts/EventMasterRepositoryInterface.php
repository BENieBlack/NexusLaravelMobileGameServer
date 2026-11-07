<?php

namespace NexusEvent\Contracts;

use NexusEvent\DataTransferObjects\Event;
use NexusEvent\DataTransferObjects\EventContent;

/**
 * EventMasterRepositoryInterface
 *
 * イベントのマスターデータ（mst_event / mst_event_content）を読む。
 * イベントは数が少ないので、全件を返してよい（リクエスト内でメモリに持つ）。
 */
interface EventMasterRepositoryInterface
{
    /**
     * @return array<int, Event>
     */
    public function selectAllEvents(): array;

    /**
     * @return array<int, EventContent>
     */
    public function selectAllContents(): array;
}
