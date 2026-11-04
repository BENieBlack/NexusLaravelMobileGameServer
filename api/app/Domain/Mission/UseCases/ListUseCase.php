<?php

namespace App\Domain\Mission\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Mission\Responses\ListResponse;
use NexusMission\Services\MissionService;

/**
 * ListUseCase
 *
 * ミッション一覧取得
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * 期間内のミッションの進捗・受け取り状況・報酬を取得する
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     */
    public function handle(int $sysPlayerId): ListResponse
    {
        return ListResponse::fromResult($this->missionService->findStatuses($sysPlayerId));
    }
}
