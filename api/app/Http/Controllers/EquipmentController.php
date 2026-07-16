<?php

namespace App\Http\Controllers;

use App\Domain\Equipment\UseCases\LevelUpUseCase;
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;
use App\Http\Requests\Equipment\LevelUpRequest;
use App\Http\Responses\Equipment\LevelUpResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * EquipmentController
 * 
 * 装備関連のAPIエンドポイント
 */
class EquipmentController extends _BaseController
{
    public function __construct(
        private readonly LevelUpUseCase $equipmentLevelUpUseCase,
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * 装備レベルアップ
     * 
     * POST /api/equipment/level_up
     * 
     * 装備経験値アイテムを消費して装備を指定レベルまでレベルアップする
     *
     * @param LevelUpRequest $request
     * @return JsonResponse
     */
    public function levelUp(LevelUpRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $sysPlayerId = $this->apiSession->getSysPlayerId();
            
            // UseCaseから直接Responseが返る
            return $this->equipmentLevelUpUseCase->exec(
                sysPlayerId: $sysPlayerId,
                trxEquipmentId: $request->getTrxEquipmentId(),
                afterLevel: $request->getAfterLevel(),
            );
        });
    }
}
