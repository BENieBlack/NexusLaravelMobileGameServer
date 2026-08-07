<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildApplyListResponse;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Services\GuildService;

/**
 * GuildApplyListUseCase
 * 
 * ギルド加入申請一覧取得ユースケース
 */
class GuildApplyListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {
    }

    /**
     * ギルド加入申請一覧取得処理を実行
     *
     * @param int $guildId ギルドID
     * @return GuildApplyListResponse
     * @throws GameException
     */
    public function exec(int $guildId): GuildApplyListResponse
    {
        try {
            // ギルド加入申請一覧を取得
            $applyDtos = $this->guildService->getApplyList($guildId);

            return GuildApplyListResponse::fromDtoArray($applyDtos);
        } catch (GuildException $e) {
            $errorCode = match (true) {
                str_contains($e->getMessage(), 'Guild not found') => GameErrorCode::GUILD_NOT_FOUND,
                default => GameErrorCode::INTERNAL_ERROR,
            };
            throw new GameException($errorCode, $e->getMessage());
        }
    }
}
