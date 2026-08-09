<?php

namespace App\Http\Controllers;

use App\Domain\Gacha\UseCases\GachaDrawUseCase;
use App\Http\Requests\Gacha\DrawRequest;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * GachaController
 *
 * ガチャ関連のAPIエンドポイント
 */
class GachaController extends _BaseController
{
    public function __construct(
        private readonly GachaDrawUseCase $drawUseCase,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * ガチャ実行
     *
     * POST /api/gacha/draw
     *
     * ガチャを実行して景品を獲得する
     */
    public function draw(DrawRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $sysPlayerId = $this->apiSession->getSysPlayerId();

            return $this->drawUseCase->exec(
                sysPlayerId: $sysPlayerId,
                mstGachaId: $request->getMstGachaId(),
                drawCount: $request->getDrawCount(),
                selectedCandidateId: $request->getSelectedCandidateId(),
            );
        });
    }
}
