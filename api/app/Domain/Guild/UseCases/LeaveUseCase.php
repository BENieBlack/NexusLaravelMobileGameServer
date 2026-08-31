<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildLeaveResponse;
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
    public function exec(int $sysPlayerId): GuildLeaveResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            // 脱退は戻り値を持たないため、応答は翻訳の外で組む
            GuildExceptionTranslator::forLeave(function () use ($sysPlayerId) {
                $this->guildService->leaveGuild($sysPlayerId);
            });

            return new GuildLeaveResponse($sysPlayerId);
        });
    }
}
