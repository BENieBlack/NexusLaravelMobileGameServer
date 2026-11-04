<?php

namespace App\Domain\Mission\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Mission\Responses\ReceiveResponse;
use App\Domain\Mission\Support\MissionExceptionTranslator;
use App\Exceptions\GameException;
use NexusMission\Services\MissionService;

/**
 * ReceiveUseCase
 *
 * 達成したミッションの報酬を受け取る
 */
class ReceiveUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @param  string  $mstMissionId  mst_mission.id
     *
     * @throws GameException 存在しない・期間外・未達成・受け取り済みの場合
     */
    public function handle(int $sysPlayerId, string $mstMissionId): ReceiveResponse
    {
        $missionStatus = $this->executeWithTransaction(
            fn () => MissionExceptionTranslator::translate(
                fn () => $this->missionService->receive($sysPlayerId, $mstMissionId)
            )
        );

        return ReceiveResponse::from($missionStatus);
    }
}
