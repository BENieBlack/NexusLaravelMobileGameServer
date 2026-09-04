<?php

namespace App\Domain\RewardTrack\UseCases;

use App\Persistence\ApiSession;
use App\Traits\UseCaseTrait;
use NexusRewardTrack\Services\RewardTrackService;

class GetSummaryUseCase
{
    use UseCaseTrait;

    public function __construct(
        private readonly RewardTrackService $rewardTrackService,
    ) {}

    public function handle(string $trackId): array
    {
        $sysPlayerId = ApiSession::getSysPlayerId();
        $connectionName = ApiSession::getShardConnectionName();

        return $this->rewardTrackService->getSummary($sysPlayerId, $trackId, $connectionName);
    }
}
