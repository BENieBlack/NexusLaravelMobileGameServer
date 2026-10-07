<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use NexusGuild\Services\GuildService;

/**
 * LeaveUseCase
 *
 * ギルド脱退ユースケース
 */
class LeaveUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルド脱退処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId): void
    {
        // トランザクション開始
        $this->executeWithTransaction(function () use ($sysPlayerId) {
            GuildExceptionTranslator::forLeave(function () use ($sysPlayerId) {
                $this->guildService->leaveGuild($sysPlayerId);
            });
        });
    }
}
