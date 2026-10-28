<?php

namespace NexusMission\DataTransferObjects;

/**
 * Mission
 *
 * ミッション定義（mst_mission の1行）
 *
 * 表示名・説明は mst_mission__l10n にあり、クライアントはマスターデータから引く。
 */
class Mission
{
    public function __construct(
        private readonly string $id,
        private readonly string $triggerType,
        private readonly ?string $mstTagId,
        private readonly int $targetValue,
        private readonly string $startAt,
        private readonly ?string $endAt,
        private readonly int $sortOrder,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 数える出来事の種類
     */
    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    /**
     * 数える対象のタグ（nullはタグを問わない）
     */
    public function getMstTagId(): ?string
    {
        return $this->mstTagId;
    }

    /**
     * 進捗を引く trx_progress.sub_target_id
     *
     * タグの指定が無ければ、タグを問わない通算を見る
     */
    public function getSubTargetId(): string
    {
        return $this->mstTagId ?? Progress::SUB_TARGET_ALL;
    }

    /**
     * 達成に必要な回数
     */
    public function getTargetValue(): int
    {
        return $this->targetValue;
    }

    /**
     * 表示・受け取りの開始日時（Y-m-d H:i:s形式）
     */
    public function getStartAt(): string
    {
        return $this->startAt;
    }

    /**
     * 表示・受け取りの終了日時（Y-m-d H:i:s形式、nullは無期限）
     */
    public function getEndAt(): ?string
    {
        return $this->endAt;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
