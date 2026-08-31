<?php

namespace App\Domain\Guild\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameException;
use App\Http\Responses\Guild\GuildCreateResponse;
use NexusGuild\Services\GuildService;

/**
 * CreateUseCase
 *
 * ギルド作成ユースケース
 */
class CreateUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly GuildService $guildService,
    ) {}

    /**
     * ギルド作成処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, string $name, string $description): GuildCreateResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $name, $description) {
            return GuildExceptionTranslator::forCreate(function () use ($sysPlayerId, $name, $description) {
                // ギルド作成（Service経由でバリデーション含む）
                $guild = $this->guildService->createGuild($name, $description, $sysPlayerId);

                // レスポンスを返す
                return GuildCreateResponse::fromDto($guild);
            });
        });
    }
}
