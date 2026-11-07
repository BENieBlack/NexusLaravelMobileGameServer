<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * RewardTrackSummary
 *
 * プレイヤーから見た報酬トラックのまとめ（マスター・進捗・所持ライン・受け取り済み）を表すDTO
 */
class RewardTrackSummary
{
    /**
     * @param  list<RewardTrackLineMaster>  $lines
     * @param  list<RewardTrackMilestoneMaster>  $milestones
     * @param  array<string, array<string, list<RewardTrackContentMaster>>>  $contents  マイルストーンID => ラインID => 報酬
     * @param  list<string>  $ownedLineIds
     * @param  array<string, bool>  $receivedKeySet  "マイルストーンID:ラインID" => true
     */
    public function __construct(
        private readonly RewardTrackMaster $track,
        private readonly array $lines,
        private readonly array $milestones,
        private readonly array $contents,
        private readonly int $currentProgress,
        private readonly array $ownedLineIds,
        private readonly array $receivedKeySet,
    ) {}

    public function getTrack(): RewardTrackMaster
    {
        return $this->track;
    }

    /**
     * @return list<RewardTrackLineMaster>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<RewardTrackMilestoneMaster>
     */
    public function getMilestones(): array
    {
        return $this->milestones;
    }

    /**
     * 指定したマイルストーンの、ライン別の報酬
     *
     * @return array<string, list<RewardTrackContentMaster>> ラインID => 報酬
     */
    public function getContentsByLine(string $milestoneId): array
    {
        return $this->contents[$milestoneId] ?? [];
    }

    public function getCurrentProgress(): int
    {
        return $this->currentProgress;
    }

    /**
     * @return list<string>
     */
    public function getOwnedLineIds(): array
    {
        return $this->ownedLineIds;
    }

    /**
     * @return array<string, bool>
     */
    public function getReceivedKeySet(): array
    {
        return $this->receivedKeySet;
    }
}
