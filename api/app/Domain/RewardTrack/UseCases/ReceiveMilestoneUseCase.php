<?php

namespace App\Domain\RewardTrack\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\RewardTrack\Responses\ReceiveMilestoneResponse;
use App\Domain\RewardTrack\Support\RewardTrackExceptionTranslator;
use App\Persistence\ApiSession;
use NexusRewardTrack\Services\RewardTrackService;

class ReceiveMilestoneUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly RewardTrackService $rewardTrackService,
    ) {}

    public function handle(string $milestoneId, string $lineId): ReceiveMilestoneResponse
    {
        $sysPlayerId = ApiSession::getSysPlayerId();
        $connectionName = ApiSession::resolveConnectionName();

        $milestone = $this->executeWithTransaction(
            fn () => RewardTrackExceptionTranslator::translate(
                fn () => $this->rewardTrackService->receiveMilestone(
                    $sysPlayerId,
                    $milestoneId,
                    $lineId,
                    $connectionName
                )
            )
        );

        return ReceiveMilestoneResponse::from($milestone);
    }
}
