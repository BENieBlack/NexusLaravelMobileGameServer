<?php

namespace App\Http\Controllers;

use App\Domain\Album\UseCases\ListUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Requests\Album\ListRequest;
use Illuminate\Http\JsonResponse;

class AlbumController extends _BaseController
{
    /**
     * アルバム一覧取得API
     *
     * 記録済みの対象と、種別ごとの収集状況を返す
     */
    public function list(ListRequest $request, ListUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        return $this->execute(fn () => $useCase->exec($sysPlayerId));
    }
}
