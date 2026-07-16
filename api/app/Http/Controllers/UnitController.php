<?php

namespace App\Http\Controllers;

use App\Domain\Unit\UseCases\LevelUpUseCase;
use App\Http\Requests\Unit\LevelUpRequest;
use App\Http\Responses\Unit\LevelUpResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * UnitController
 * 
 * ユニット関連のAPIエンドポイント
 */
class UnitController extends _BaseController
{
    public function __construct(
        private readonly LevelUpUseCase $unitLevelUpUseCase,
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * ユニットレベルアップ
     * 
     * POST /api/unit/level_up
     * 
     * ユニット経験値アイテムを消費してユニットの経験値を上げる
     *
     * @param LevelUpRequest $request
     * @return JsonResponse
     */
    public function levelUp(LevelUpRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $sysPlayerId = $this->apiSession->getSysPlayerId();
            
            // UseCaseから直接Responseが返る
            return $this->unitLevelUpUseCase->exec(
                sysPlayerId: $sysPlayerId,
                trxUnitId: $request->getTrxUnitId(),
                mstItemId: $request->getMstItemId(),
                useCount: $request->getUseCount(),
            );
        });
    }
}
