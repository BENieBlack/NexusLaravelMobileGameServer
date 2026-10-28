<?php

namespace App\Http\Controllers;

use App\Domain\Mission\UseCases\ListUseCase;
use App\Domain\Mission\UseCases\ReceiveUseCase;
use App\Http\Requests\Mission\ListRequest;
use App\Http\Requests\Mission\ReceiveRequest;
use App\Http\Responses\Mission\ListResponse;
use App\Http\Responses\Mission\ReceiveResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * MissionController
 *
 * ミッション関連のAPIエンドポイント
 */
class MissionController extends _BaseController
{
    public function __construct(
        private readonly ListUseCase $listUseCase,
        private readonly ReceiveUseCase $receiveUseCase,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * ミッション一覧取得
     *
     * GET /api/mission/list
     */
    public function list(ListRequest $request): JsonResponse
    {
        return $this->execute(fn () => ListResponse::fromDtoArray(
            $this->listUseCase->exec($this->apiSession->getSysPlayerId())
        ));
    }

    /**
     * ミッション報酬受け取り
     *
     * POST /api/mission/receive
     */
    public function receive(ReceiveRequest $request): JsonResponse
    {
        return $this->execute(fn () => ReceiveResponse::fromDto($this->receiveUseCase->exec(
            sysPlayerId: $this->apiSession->getSysPlayerId(),
            mstMissionId: $request->getMstMissionId(),
        )));
    }
}
