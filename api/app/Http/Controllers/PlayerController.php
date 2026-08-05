<?php

namespace App\Http\Controllers;

use App\Domain\Player\UseCases\PlayerMeUseCase;
use App\Http\Requests\Player\MeRequest;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

class PlayerController extends _BaseController
{
    public function __construct(
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * 認証済みプレイヤー情報取得（認証必須）
     */
    public function me(MeRequest $request, PlayerMeUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        return $this->execute(fn() => $useCase->exec($sysPlayerId));
    }
}
