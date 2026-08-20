<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildApplyRejectResponse;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Services\GuildService;

/**
 * ApplyRejectUseCase
 *
 * ギルド加入申請却下ユースケース
 */
class ApplyRejectUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルド加入申請却下処理を実行
     *
     * @param  int  $sysPlayerId  却下するプレイヤーID（マスター/サブマスター）
     * @param  int  $applyId  申請ID
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $applyId): GuildApplyRejectResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $applyId) {
            try {
                // 申請却下（Service経由でバリデーション含む）
                $apply = $this->guildService->rejectApply($applyId, $sysPlayerId);

                // レスポンスを返す
                return GuildApplyRejectResponse::fromDto($apply);
            } catch (GuildException $e) {
                // パッケージの例外をGameExceptionに変換
                $errorCode = match (true) {
                    str_contains($e->getMessage(), 'Apply not found') => GameErrorCode::GUILD_APPLY_NOT_FOUND,
                    str_contains($e->getMessage(), 'Member not found') => GameErrorCode::GUILD_MEMBER_NOT_FOUND,
                    str_contains($e->getMessage(), 'permission') => GameErrorCode::GUILD_PERMISSION_DENIED,
                    str_contains($e->getMessage(), 'Invalid status') => GameErrorCode::GUILD_INVALID_STATUS,
                    default => GameErrorCode::GUILD_APPLY_REJECT_FAILED,
                };
                throw new GameException($errorCode, $e->getMessage());
            }
        });
    }
}
