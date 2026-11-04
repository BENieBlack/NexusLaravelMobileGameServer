<?php

namespace App\Domain\RewardTrack\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\RewardTrack\Responses\GetSummaryResponse;
use App\Domain\RewardTrack\Support\RewardTrackExceptionTranslator;
use App\Persistence\ApiSession;
use NexusRewardTrack\Services\RewardTrackService;

class GetSummaryUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly RewardTrackService $rewardTrackService,
    ) {}

    public function handle(string $trackId): GetSummaryResponse
    {
        $sysPlayerId = ApiSession::getSysPlayerId();
        $connectionName = ApiSession::resolveConnectionName();

        return GetSummaryResponse::fromResult(RewardTrackExceptionTranslator::translate(
            fn () => $this->rewardTrackService->getSummary($sysPlayerId, $trackId, $connectionName)
        ));
    }
}
