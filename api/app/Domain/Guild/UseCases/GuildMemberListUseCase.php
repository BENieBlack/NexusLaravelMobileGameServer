<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildMemberListResponse;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Services\GuildService;

/**
 * GuildMemberListUseCase
 *
 * ギルドメンバー一覧取得ユースケース
 */
class GuildMemberListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルドメンバー一覧取得処理を実行
     *
     * @param  int  $guildId  ギルドID
     *
     * @throws GameException
     */
    public function exec(int $guildId): GuildMemberListResponse
    {
        try {
            // ギルドメンバー一覧を取得
            $memberDtos = $this->guildService->findMemberList($guildId);

            return GuildMemberListResponse::fromDtoArray($memberDtos);
        } catch (GuildException $e) {
            $errorCode = match (true) {
                str_contains($e->getMessage(), 'Guild not found') => GameErrorCode::GUILD_NOT_FOUND,
                default => GameErrorCode::INTERNAL_ERROR,
            };
            throw new GameException($errorCode, $e->getMessage());
        }
    }
}
