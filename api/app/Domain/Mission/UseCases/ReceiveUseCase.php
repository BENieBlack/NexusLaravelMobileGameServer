<?php

namespace App\Domain\Mission\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Mission\Support\MissionExceptionTranslator;
use App\Exceptions\GameException;
use NexusMission\Services\MissionService;
use NexusMission\ValueObjects\MissionStatus;

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
     * @return MissionStatus 受け取り後の状態（配った報酬を含む）
     *
     * @throws GameException 存在しない・期間外・未達成・受け取り済みの場合
     */
    public function handle(int $sysPlayerId, string $mstMissionId): MissionStatus
    {
        return $this->executeWithTransaction(
            fn () => MissionExceptionTranslator::translate(
                fn () => $this->missionService->receive($sysPlayerId, $mstMissionId)
            )
        );
    }
}
