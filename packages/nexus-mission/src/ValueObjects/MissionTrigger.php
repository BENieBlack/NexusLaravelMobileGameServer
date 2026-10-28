<?php

namespace NexusMission\ValueObjects;

use InvalidArgumentException;
use NexusMission\DataTransferObjects\Progress;

/**
 * MissionTrigger
 *
 * 進捗を数える出来事（「Vガンダムが完凸した」など）
 *
 * 対象が持つタグを一緒に渡し、タグごとのカウンタとタグを問わない通算を進める。
 * タグは呼び出し側が引いて渡すので、このパッケージはタグのマスターを知らない。
 */
final class MissionTrigger
{
    /**
     * @param  string  $triggerType  出来事の種類（mst_mission.trigger_type と同じ値）
     * @param  array<int, string>  $mstTagIds  対象が持つタグID
     * @param  int  $amount  進める量（1回の出来事で何件分か）
     */
    public function __construct(
        private readonly string $triggerType,
        private readonly array $mstTagIds = [],
        private readonly int $amount = 1,
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException("進める量は1以上にしてください: {$amount}");
        }
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * 進めるカウンタの sub_target_id
     *
     * 対象が持つタグすべてと、タグを問わない通算（all）。同じタグが重なっても1回だけ数える。
     *
     * @return array<int, string>
     */
    public function findSubTargetIds(): array
    {
        return array_values(array_unique([...$this->mstTagIds, Progress::SUB_TARGET_ALL]));
    }
}
