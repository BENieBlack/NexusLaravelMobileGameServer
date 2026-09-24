<?php

namespace App\Domain\Guild\UseCases;

use App\Adapters\Guild\GuildAdapter;
use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildDetailResponse;
use App\Repositories\Sys\SysGuildRepository;

/**
 * DetailUseCase
 *
 * ギルド詳細取得ユースケース
 */
class DetailUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysGuildRepository $sysGuildRepository,
    ) {}

    /**
     * ギルド詳細取得処理を実行
     *
     * @param  int  $guildId  ギルドID
     *
     * @throws GameException
     */
    public function exec(int $guildId): GuildDetailResponse
    {
        // RepositoryはModelを返すため、DTOへの変換はここで行う
        $guild = $this->sysGuildRepository->selectById($guildId);

        if ($guild === null) {
            throw new GameException(
                GameErrorCode::GUILD_NOT_FOUND,
                "Guild not found: {$guildId}"
            );
        }

        return GuildDetailResponse::fromDto(GuildAdapter::toDto($guild));
    }
}
