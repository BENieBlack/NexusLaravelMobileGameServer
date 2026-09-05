<?php

namespace App\Domain\RewardTrack\UseCases;

use App\Persistence\ApiSession;
use App\Traits\UseCaseTrait;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;
use NexusRewardTrack\Services\RewardTrackService;

class ReceiveMilestoneUseCase
{
    use UseCaseTrait;

    public function __construct(
        private readonly RewardTrackService $rewardTrackService,
    ) {}

    public function handle(string $milestoneId, string $lineId): RewardTrackMilestone
    {
        $sysPlayerId = ApiSession::getSysPlayerId();
        $connectionName = ApiSession::resolveConnectionName();

        return $this->executeWithTransaction(
            function () use ($sysPlayerId, $milestoneId, $lineId, $connectionName) {
                return $this->rewardTrackService->receiveMilestone(
                    $sysPlayerId,
                    $milestoneId,
                    $lineId,
                    $connectionName
                );
            }
        );
    }
}
