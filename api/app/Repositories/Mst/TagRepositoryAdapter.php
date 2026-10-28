<?php

namespace App\Repositories\Mst;

use App\Adapters\Tag\TagContentAdapter;
use NexusTag\Repositories\TagRepositoryInterface;

/**
 * TagRepositoryAdapter
 *
 * MstTagContentRepository（Model）と TagRepositoryInterface（DTO）の橋渡し
 */
class TagRepositoryAdapter implements TagRepositoryInterface
{
    public function __construct(
        private readonly MstTagContentRepository $mstTagContentRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectAllContents(): array
    {
        return TagContentAdapter::toDtoArray($this->mstTagContentRepository->queryOrMemory()->values());
    }
}
