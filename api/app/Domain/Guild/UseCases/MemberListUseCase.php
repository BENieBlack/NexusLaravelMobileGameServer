<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Responses\MemberListResponse;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use NexusGuild\Services\GuildService;

/**
 * MemberListUseCase
 *
 * ギルドメンバー一覧取得ユースケース
 */
class MemberListUseCase extends _BaseUseCase
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
    public function handle(int $guildId): MemberListResponse
    {
        $guildMembers = GuildExceptionTranslator::forRead(function () use ($guildId) {
            // ギルドメンバー一覧を取得
            $memberDtos = $this->guildService->findMemberList($guildId);

            return $memberDtos;
        });

        return MemberListResponse::from($guildMembers);
    }
}
