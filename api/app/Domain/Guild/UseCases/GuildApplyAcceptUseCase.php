<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildApplyAcceptResponse;
use App\Repositories\Sys\SysGuildApplyRepository;
use App\Repositories\Sys\SysGuildMemberRepository;
use NexusGuild\Constants\GuildRole;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Services\GuildService;

/**
 * GuildApplyAcceptUseCase
 * 
 * ギルド加入申請承認ユースケース
 */
class GuildApplyAcceptUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysGuildApplyRepository $sysGuildApplyRepository,
        private readonly SysGuildMemberRepository $sysGuildMemberRepository,
        private readonly GuildService $guildService,
    ) {
    }

    /**
     * ギルド加入申請承認処理を実行
     *
     * @param int $sysPlayerId 承認するプレイヤーID（マスター/サブマスター）
     * @param int $applyId 申請ID
     * @return GuildApplyAcceptResponse
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $applyId): GuildApplyAcceptResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $applyId) {
            try {
                // 申請承認（Service経由でバリデーション含む）
                $applyDto = $this->guildService->acceptApply($applyId, $sysPlayerId);

                // メンバーとして追加
                $this->sysGuildMemberRepository->create(
                    $applyDto->getSysGuildId(),
                    $applyDto->getSysPlayerId(),
                    GuildRole::MEMBER
                );

                // レスポンスを返す
                return GuildApplyAcceptResponse::fromDto($applyDto);
            } catch (GuildException $e) {
                // パッケージの例外をGameExceptionに変換
                $errorCode = match (true) {
                    str_contains($e->getMessage(), 'Apply not found') => GameErrorCode::GUILD_APPLY_NOT_FOUND,
                    str_contains($e->getMessage(), 'Member not found') => GameErrorCode::GUILD_MEMBER_NOT_FOUND,
                    str_contains($e->getMessage(), 'permission') => GameErrorCode::GUILD_PERMISSION_DENIED,
                    str_contains($e->getMessage(), 'full') => GameErrorCode::GUILD_FULL,
                    str_contains($e->getMessage(), 'Invalid status') => GameErrorCode::GUILD_INVALID_STATUS,
                    default => GameErrorCode::GUILD_APPLY_ACCEPT_FAILED,
                };
                throw new GameException($errorCode, $e->getMessage());
            }
        });
    }
}
