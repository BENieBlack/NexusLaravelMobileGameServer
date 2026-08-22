<?php

namespace App\Repositories\Trx;

use NexusResourceDelivery\Contracts\UnitRepositoryInterface;

/**
 * UnitRepositoryAdapter
 *
 * nexus-resource-deliveryのUnitRepositoryInterfaceを実装し、
 * Application層のTrxUnitRepositoryをラップする。
 *
 * 配送はログインセッションの本人以外（運営からの一斉配布など）にも走るため、
 * 付与先を明示できる insertUnitForPlayer() へ委譲する。
 */
class UnitRepositoryAdapter implements UnitRepositoryInterface
{
    public function __construct(
        private readonly TrxUnitRepository $trxUnitRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function insertUnit(int $sysPlayerId, string $mstUnitId, ?int $grade = null, ?int $level = null): void
    {
        $this->trxUnitRepository->insertUnitForPlayer($sysPlayerId, $mstUnitId, $grade, $level);
    }
}
