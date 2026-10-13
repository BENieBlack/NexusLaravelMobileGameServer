<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use NexusGuild\DataTransferObjects\GuildApply;
use NexusGuild\Services\GuildService;

/**
 * ApplyListUseCase
 *
 * ギルド加入申請一覧取得ユースケース
 */
class ApplyListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルド加入申請一覧取得処理を実行
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildApply>
     *
     * @throws GameException
     */
    public function exec(int $guildId): array
    {
        return GuildExceptionTranslator::forRead(function () use ($guildId) {
            // ギルド加入申請一覧を取得
            $applyDtos = $this->guildService->findApplyList($guildId);

            return $applyDtos;
        });
    }
}
