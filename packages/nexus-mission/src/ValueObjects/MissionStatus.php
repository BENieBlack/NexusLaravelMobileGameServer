<?php

namespace NexusMission\ValueObjects;

use NexusMission\DataTransferObjects\Mission;
use NexusMission\DataTransferObjects\MissionContent;
use NexusMission\DataTransferObjects\MissionReceipt;

/**
 * MissionStatus
 *
 * プレイヤーから見たミッション1件の状態（定義・進捗・受け取り・報酬）
 */
final class MissionStatus
{
    /**
     * @param  int  $progressValue  カウンタの通算値（目標値を超えていることもある）
     * @param  MissionReceipt|null  $missionReceipt  受け取り記録（未受け取りならnull）
     * @param  array<int, MissionContent>  $missionContents  達成報酬
     */
    public function __construct(
        private readonly Mission $mission,
        private readonly int $progressValue,
        private readonly ?MissionReceipt $missionReceipt,
        private readonly array $missionContents,
    ) {}

    public function getMission(): Mission
    {
        return $this->mission;
    }

    /**
     * @return array<int, MissionContent>
     */
    public function getMissionContents(): array
    {
        return $this->missionContents;
    }

    /**
     * 表示用の進捗値（目標値で頭打ち）
     *
     * カウンタは他のミッションと共有の通算値なので、目標値を超えることがある
     */
    public function getProgressValue(): int
    {
        return min($this->progressValue, $this->mission->getTargetValue());
    }

    /**
     * 目標値に達したか
     */
    public function isCompleted(): bool
    {
        return $this->progressValue >= $this->mission->getTargetValue();
    }

    public function isReceived(): bool
    {
        return $this->missionReceipt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mst_mission_id' => $this->mission->getId(),
            'progress_value' => $this->getProgressValue(),
            'target_value' => $this->mission->getTargetValue(),
            'is_completed' => $this->isCompleted(),
            'is_received' => $this->isReceived(),
            'received_at' => $this->missionReceipt?->getReceivedAt(),
            'end_at' => $this->mission->getEndAt(),
            'content_list' => array_map(
                fn (MissionContent $missionContent) => $missionContent->toArray(),
                $this->missionContents,
            ),
        ];
    }
}
