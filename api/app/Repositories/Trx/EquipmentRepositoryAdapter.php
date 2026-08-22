<?php

namespace App\Repositories\Trx;

use NexusResourceDelivery\Contracts\EquipmentRepositoryInterface;

/**
 * EquipmentRepositoryAdapter
 *
 * nexus-resource-deliveryのEquipmentRepositoryInterfaceを実装し、
 * Application層のTrxEquipmentRepositoryをラップする。
 *
 * 配送はログインセッションの本人以外（運営からの一斉配布など）にも走るため、
 * 付与先を明示できる insertEquipmentForPlayer() へ委譲する。
 */
class EquipmentRepositoryAdapter implements EquipmentRepositoryInterface
{
    public function __construct(
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function insertEquipment(int $sysPlayerId, string $mstEquipmentId, ?int $level = null, ?int $grade = null): void
    {
        $this->trxEquipmentRepository->insertEquipmentForPlayer($sysPlayerId, $mstEquipmentId, $level, $grade);
    }
}
