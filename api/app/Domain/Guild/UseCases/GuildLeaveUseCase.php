<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Services\GuildService;

/**
 * GuildLeaveUseCase
 *
 * ギルド脱退ユースケース
 */
class GuildLeaveUseCase extends _BaseUseCase
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
            try {
                // ギルド脱退（Service経由でバリデーション含む）
                $this->guildService->leaveGuild($sysPlayerId);
            } catch (GuildException $e) {
                // パッケージの例外をGameExceptionに変換
                $errorCode = match (true) {
                    str_contains($e->getMessage(), 'not in any guild') => GameErrorCode::PLAYER_NOT_IN_GUILD,
                    str_contains($e->getMessage(), 'Master cannot leave') => GameErrorCode::GUILD_MASTER_CANNOT_LEAVE,
                    default => GameErrorCode::GUILD_LEAVE_FAILED,
                };
                throw new GameException($errorCode, $e->getMessage());
            }
        });
    }
}
