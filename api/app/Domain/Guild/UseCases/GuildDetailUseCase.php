<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildDetailResponse;
use App\Repositories\Sys\SysGuildRepository;

/**
 * GuildDetailUseCase
 * 
 * ギルド詳細取得ユースケース
 */
class GuildDetailUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysGuildRepository $sysGuildRepository,
    ) {
    }

    /**
     * ギルド詳細取得処理を実行
     *
     * @param int $guildId ギルドID
     * @return GuildDetailResponse
     * @throws GameException
     */
    public function exec(int $guildId): GuildDetailResponse
    {
        // ギルド情報を取得
        $guildDto = $this->sysGuildRepository->findById($guildId);

        if ($guildDto === null) {
            throw new GameException(
                GameErrorCode::GUILD_NOT_FOUND,
                "Guild not found: {$guildId}"
            );
        }

        return GuildDetailResponse::fromDto($guildDto);
    }
}
