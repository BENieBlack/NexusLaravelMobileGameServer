<?php

namespace App\Repositories\Trx;

use App\Adapters\Mission\ProgressAdapter;
use NexusMission\DataTransferObjects\Progress;
use NexusMission\Repositories\ProgressRepositoryInterface;

/**
 * ProgressRepositoryAdapter
 *
 * TrxProgressRepository（Model）と ProgressRepositoryInterface（DTO）の橋渡し
 */
class ProgressRepositoryAdapter implements ProgressRepositoryInterface
{
    public function __construct(
        private readonly TrxProgressRepository $trxProgressRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $sysPlayerId): array
    {
        return ProgressAdapter::toDtoArray($this->trxProgressRepository->selectAllByPlayer()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function save(Progress $progress): void
    {
        $this->trxProgressRepository->upsertProgressValue(
            $progress->getSysPlayerId(),
            $progress->getTriggerType(),
            $progress->getSubTargetId(),
            $progress->getProgressValue(),
        );
    }
}
