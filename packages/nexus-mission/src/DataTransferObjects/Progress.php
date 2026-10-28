<?php

namespace NexusMission\DataTransferObjects;

/**
 * Progress
 *
 * 出来事 × 対象ごとの通算カウンタ（trx_progress の1行）
 */
class Progress
{
    /**
     * タグを問わない通算を表す sub_target_id
     */
    public const SUB_TARGET_ALL = 'all';

    public function __construct(
        private readonly int $sysPlayerId,
        private readonly string $triggerType,
        private readonly string $subTargetId,
        private readonly int $progressValue,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    /**
     * 数える対象（mst_tag.id か all）
     */
    public function getSubTargetId(): string
    {
        return $this->subTargetId;
    }

    public function getProgressValue(): int
    {
        return $this->progressValue;
    }

    /**
     * 同じカウンタを指しているか
     */
    public function isFor(string $triggerType, string $subTargetId): bool
    {
        return $this->triggerType === $triggerType && $this->subTargetId === $subTargetId;
    }
}
