<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildApplyAcceptResponse;
use App\Repositories\Sys\SysGuildMemberRepository;
use NexusGuild\Constants\GuildRole;
use NexusGuild\Services\GuildService;

/**
 * ApplyAcceptUseCase
 *
 * ギルド加入申請承認ユースケース
 */
class ApplyAcceptUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysGuildMemberRepository $sysGuildMemberRepository,
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルド加入申請承認処理を実行
     *
     * @param  int  $sysPlayerId  承認するプレイヤーID（マスター/サブマスター）
     * @param  int  $applyId  申請ID
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $applyId): GuildApplyAcceptResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $applyId) {
            return GuildExceptionTranslator::forApplyAccept(function () use ($sysPlayerId, $applyId) {
                // 申請承認（Service経由でバリデーション含む）
                $apply = $this->guildService->acceptApply($applyId, $sysPlayerId);

                // メンバーとして追加
                $this->sysGuildMemberRepository->insertMember(
                    $apply->getSysGuildId(),
                    $apply->getSysPlayerId(),
                    GuildRole::MEMBER
                );

                // レスポンスを返す
                return GuildApplyAcceptResponse::fromDto($apply);
            });
        });
    }
}
