<?php

namespace App\Adapters\Event;

use App\Models\Mst\MstEvent;
use NexusEvent\DataTransferObjects\Event;

/**
 * EventAdapter
 *
 * MstEvent Model と Event の変換を行うアダプター
 */
class EventAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstEvent $mstEvent): Event
    {
        return new Event(
            id: $mstEvent->getId(),
            startAt: $mstEvent->getStartAt(),
            endAt: $mstEvent->getEndAt(),
            exchangeEndAt: $mstEvent->getExchangeEndAt(),
            sortOrder: $mstEvent->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstEvent>  $mstEvents
     * @return array<int, Event>
     */
    public static function toDtoArray(iterable $mstEvents): array
    {
        $dtos = [];

        foreach ($mstEvents as $mstEvent) {
            $dtos[] = self::toDto($mstEvent);
        }

        return $dtos;
    }
}
