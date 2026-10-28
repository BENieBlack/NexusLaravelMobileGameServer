<?php

namespace App\Repositories\Trx;

use App\Adapters\Mission\MissionReceiptAdapter;
use NexusMission\DataTransferObjects\MissionReceipt;
use NexusMission\Repositories\MissionReceiptRepositoryInterface;

/**
 * MissionReceiptRepositoryAdapter
 *
 * TrxMissionRepository（Model）と MissionReceiptRepositoryInterface（DTO）の橋渡し
 */
class MissionReceiptRepositoryAdapter implements MissionReceiptRepositoryInterface
{
    public function __construct(
        private readonly TrxMissionRepository $trxMissionRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $sysPlayerId): array
    {
        return MissionReceiptAdapter::toDtoArray($this->trxMissionRepository->selectAllByPlayer()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function selectByMissionId(int $sysPlayerId, string $mstMissionId): ?MissionReceipt
    {
        $trxMission = $this->trxMissionRepository->selectByMstMissionId($mstMissionId);

        return $trxMission === null ? null : MissionReceiptAdapter::toDto($trxMission);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(MissionReceipt $missionReceipt): void
    {
        $this->trxMissionRepository->insertReceipt(
            $missionReceipt->getSysPlayerId(),
            $missionReceipt->getMstMissionId(),
            $missionReceipt->getReceivedAt(),
        );
    }
}
