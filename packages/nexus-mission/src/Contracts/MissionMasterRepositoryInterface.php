<?php

namespace NexusMission\Contracts;

use NexusMission\DataTransferObjects\Mission;
use NexusMission\DataTransferObjects\MissionContent;

/**
 * MissionMasterRepositoryInterface
 *
 * ミッションのマスター（mst_mission / mst_mission_content）へのアクセスを抽象化する
 */
interface MissionMasterRepositoryInterface
{
    /**
     * ミッション定義を全件取得する（期間の絞り込みは MissionService が行う）
     *
     * @return array<int, Mission>
     */
    public function selectAllMissions(): array;

    /**
     * IDでミッション定義を取得する
     */
    public function selectMissionById(string $mstMissionId): ?Mission;

    /**
     * ミッションの達成報酬を取得する（sort_order昇順）
     *
     * @return array<int, MissionContent>
     */
    public function selectContentsByMissionId(string $mstMissionId): array;
}
