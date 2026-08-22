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
     * ギルド一覧取得処理を実行
     */
    public function exec(): GuildListResponse
    {
        // 全ギルド一覧を取得（Package層のServiceメソッド経由）
        // 将来的にはページネーションや検索条件を追加可能
        $guildDtos = $this->guildService->getAllGuilds();

        return GuildListResponse::fromDtoArray($guildDtos);
    }
}
