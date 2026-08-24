<?php

namespace App\Http\Controllers;

use App\Domain\Item\UseCases\UseItemUseCase;
use App\Http\Requests\Item\UseRequest;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * ItemController
 *
 * アイテム関連のAPIエンドポイント
 */
class ItemController extends _BaseController
{
    public function __construct(
        private readonly UseItemUseCase $useItemUseCase,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * アイテム使用
     *
     * POST /api/item/use
     *
     * アイテムを消費して mst_item.effect に応じた効果を適用する。
     * ユニット・装備の経験値アイテムは対象の指定が要るため、
     * それぞれ /unit/level_up と /equipment/level_up を使う。
     */
    public function use(UseRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            return $this->useItemUseCase->exec(
                sysPlayerId: $this->apiSession->getSysPlayerId(),
                mstItemId: $request->getMstItemId(),
                useCount: $request->getUseCount(),
            );
        });
    }
}
