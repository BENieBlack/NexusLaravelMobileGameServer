<?php

namespace App\Domain\RewardTrack\UseCases;

use App\Domain\RewardTrack\Support\RewardTrackExceptionTranslator;
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
            fn () => RewardTrackExceptionTranslator::translate(
                fn () => $this->rewardTrackService->receiveMilestone(
                    $sysPlayerId,
                    $milestoneId,
                    $lineId,
                    $connectionName
                )
            )
        );
    }
}
