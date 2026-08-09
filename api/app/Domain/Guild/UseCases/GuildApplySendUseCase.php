<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildApplySendResponse;
use App\Repositories\Sys\SysGuildRepository;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Services\GuildService;

/**
 * GuildApplySendUseCase
 *
 * ギルド加入申請送信ユースケース
 */
class GuildApplySendUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysGuildRepository $sysGuildRepository,
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルド加入申請送信処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $guildId  ギルドID
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $guildId): GuildApplySendResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $guildId) {
            try {
                // 申請送信（Service経由でバリデーション含む）
                $applyDto = $this->guildService->sendApply($guildId, $sysPlayerId);

                // レスポンスを返す
                return GuildApplySendResponse::fromDto($applyDto);
            } catch (GuildException $e) {
                // パッケージの例外をGameExceptionに変換
                $errorCode = match (true) {
                    str_contains($e->getMessage(), 'Guild not found') => GameErrorCode::GUILD_NOT_FOUND,
                    str_contains($e->getMessage(), 'already in a guild') => GameErrorCode::PLAYER_ALREADY_IN_GUILD,
                    str_contains($e->getMessage(), 'already exists') => GameErrorCode::GUILD_APPLY_ALREADY_EXISTS,
                    str_contains($e->getMessage(), 'full') => GameErrorCode::GUILD_FULL,
                    default => GameErrorCode::GUILD_APPLY_FAILED,
                };
                throw new GameException($errorCode, $e->getMessage());
            }
        });
    }
}
