<?php

namespace App\Domain\RewardTrack\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\RewardTrack\Support\RewardTrackExceptionTranslator;
use App\Persistence\ApiSession;
use NexusRewardTrack\Services\RewardTrackService;

class GetSummaryUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly RewardTrackService $rewardTrackService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $trackId): array
    {
        $sysPlayerId = ApiSession::getSysPlayerId();
        $connectionName = ApiSession::resolveConnectionName();

        return RewardTrackExceptionTranslator::translate(
            fn () => $this->rewardTrackService->getSummary($sysPlayerId, $trackId, $connectionName)
        );
    }
}
