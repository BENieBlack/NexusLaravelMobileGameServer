<?php

namespace App\Repositories\Mst;

use App\Adapters\Mission\MissionAdapter;
use App\Adapters\Mission\MissionContentAdapter;
use App\Models\Mst\MstMissionContent;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Services\EventPeriodResolver;
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
        private readonly EventPeriodResolver $eventPeriodResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectAllMissions(): array
    {
        return array_map(
            fn (Mission $mission) => $this->applyEventPeriod($mission),
            MissionAdapter::toDtoArray($this->mstMissionRepository->queryOrMemory()->values()),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectMissionById(string $mstMissionId): ?Mission
    {
        $mstMission = $this->mstMissionRepository->selectById($mstMissionId);

        return $mstMission === null ? null : $this->applyEventPeriod(MissionAdapter::toDto($mstMission));
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

    /**
     * ミッションがイベントに入っていれば、イベントの期間で上書きする
     *
     * 子の end_at には exchange_end_at が入る（報酬の受け取りは交換期間まで）。
     */
    private function applyEventPeriod(Mission $mission): Mission
    {
        $period = $this->eventPeriodResolver->resolve(EventContentType::MISSION, $mission->getId());

        return $period === null ? $mission : $mission->withPeriod($period->getStartAt(), $period->getEndAt());
    }
}
