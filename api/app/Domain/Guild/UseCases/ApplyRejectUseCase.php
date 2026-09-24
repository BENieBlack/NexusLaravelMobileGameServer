<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildApplyRejectResponse;
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
            return GuildExceptionTranslator::forApplyReject(function () use ($sysPlayerId, $applyId) {
                // 申請却下（Service経由でバリデーション含む）
                $apply = $this->guildService->rejectApply($applyId, $sysPlayerId);

                // レスポンスを返す
                return GuildApplyRejectResponse::fromDto($apply);
            });
        });
    }
}
