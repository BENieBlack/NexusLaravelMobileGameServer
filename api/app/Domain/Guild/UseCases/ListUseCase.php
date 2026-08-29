<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Guild\GuildListResponse;
use NexusGuild\Services\GuildService;

/**
 * ListUseCase
 *
 * ギルド一覧取得ユースケース
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {}

    /**
     * 1回に返すギルドの上限
     *
     * ギルドはゲーム内に無数にあるため、全件は決して返さない。
     */
    private const MAX_LIMIT = 50;

    /**
     * ギルド一覧取得処理を実行
     *
     * @param  int  $limit  取得件数（MAX_LIMITで頭打ち）
     * @param  int  $offset  読み飛ばす件数
     */
    public function exec(int $limit = self::MAX_LIMIT, int $offset = 0): GuildListResponse
    {
        $guildDtos = $this->guildService->findGuildList(
            max(1, min($limit, self::MAX_LIMIT)),
            max(0, $offset),
        );

        return GuildListResponse::fromDtoArray($guildDtos);
    }
}
