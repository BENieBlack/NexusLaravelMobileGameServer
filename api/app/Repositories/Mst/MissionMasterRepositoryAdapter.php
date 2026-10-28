<?php

namespace App\Repositories\Mst;

use App\Adapters\Mission\MissionAdapter;
use App\Adapters\Mission\MissionContentAdapter;
use App\Models\Mst\MstMissionContent;
use NexusMission\Contracts\MissionMasterRepositoryInterface;
use NexusMission\DataTransferObjects\Mission;

/**
 * MissionMasterRepositoryAdapter
 *
 * MstMission / MstMissionContent の Repository（Model）と
 * MissionMasterRepositoryInterface（DTO）の橋渡し
 */
class MissionMasterRepositoryAdapter implements MissionMasterRepositoryInterface
{
    public function __construct(
        private readonly MstMissionRepository $mstMissionRepository,
        private readonly MstMissionContentRepository $mstMissionContentRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectAllMissions(): array
    {
        return MissionAdapter::toDtoArray($this->mstMissionRepository->queryOrMemory()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function selectMissionById(string $mstMissionId): ?Mission
    {
        $mstMission = $this->mstMissionRepository->selectById($mstMissionId);

        return $mstMission === null ? null : MissionAdapter::toDto($mstMission);
    }

    /**
     * {@inheritDoc}
     */
    public function selectContentsByMissionId(string $mstMissionId): array
    {
        $mstMissionContents = $this->mstMissionContentRepository->queryOrMemory()
            ->filter(fn (MstMissionContent $content) => $content->getMstMissionId() === $mstMissionId)
            ->sortBy(fn (MstMissionContent $content) => $content->getSortOrder())
            ->values();

        return MissionContentAdapter::toDtoArray($mstMissionContents);
    }
}
