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
    public static function toDto(MstEvent $model): Event
    {
        return new Event(
            id: $model->getId(),
            startAt: $model->getStartAt(),
            endAt: $model->getEndAt(),
            exchangeEndAt: $model->getExchangeEndAt(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstEvent>  $models
     * @return array<int, Event>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];

        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
