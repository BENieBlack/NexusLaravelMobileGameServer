<?php

namespace App\Domain\Album\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Album\ListResponse;
use NexusAlbum\Services\AlbumService;

/**
 * ListUseCase
 *
 * アルバム一覧取得
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly AlbumService $albumService,
    ) {}

    /**
     * 記録済みの対象と、種別ごとの収集状況を取得する
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function exec(int $sysPlayerId): ListResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            return new ListResponse(
                albumEntries: $this->albumService->findEntries($sysPlayerId),
                albumProgressList: $this->albumService->findProgress($sysPlayerId),
            );
        });
    }
}
