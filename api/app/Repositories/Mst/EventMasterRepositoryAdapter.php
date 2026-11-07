<?php

namespace App\Repositories\Mst;

use App\Adapters\Event\EventAdapter;
use App\Adapters\Event\EventContentAdapter;
use NexusEvent\Contracts\EventMasterRepositoryInterface;

/**
 * EventMasterRepositoryAdapter
 *
 * MstEvent / MstEventContent の Repository（Model）と
 * EventMasterRepositoryInterface（DTO）の橋渡し
 */
class EventMasterRepositoryAdapter implements EventMasterRepositoryInterface
{
    public function __construct(
        private readonly MstEventRepository $mstEventRepository,
        private readonly MstEventContentRepository $mstEventContentRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectAllEvents(): array
    {
        return EventAdapter::toDtoArray($this->mstEventRepository->queryOrMemory()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function selectAllContents(): array
    {
        return EventContentAdapter::toDtoArray($this->mstEventContentRepository->queryOrMemory()->values());
    }
}
