<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use NexusGuild\DataTransferObjects\GuildApply;
use NexusGuild\Services\GuildService;

/**
 * ApplySendUseCase
 *
 * ギルド加入申請送信ユースケース
 */
class ApplySendUseCase extends _BaseUseCase
{
    public function __construct(
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
    public function exec(int $sysPlayerId, int $guildId): GuildApply
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $guildId) {
            return GuildExceptionTranslator::forApplySend(function () use ($sysPlayerId, $guildId) {
                // 申請送信（Service経由でバリデーション含む）
                $apply = $this->guildService->sendApply($guildId, $sysPlayerId);

                return $apply;
            });
        });
    }
}
