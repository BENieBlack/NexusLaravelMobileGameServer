<?php

namespace App\Http\Controllers;

use App\Domain\RewardTrack\UseCases\GetSummaryUseCase;
use App\Domain\RewardTrack\UseCases\ReceiveMilestoneUseCase;
use App\Http\Requests\RewardTrack\GetSummaryRequest;
use App\Http\Requests\RewardTrack\ReceiveMilestoneRequest;
use App\Http\Responses\RewardTrack\ReceiveResponse;
use App\Http\Responses\RewardTrack\SummaryResponse;
use Illuminate\Http\JsonResponse;

/**
 * RewardTrackController
 *
 * リワードトラック関連のAPIエンドポイント
 */
class RewardTrackController extends _BaseController
{
    /**
     * トラックサマリーを取得する
     *
     * GET /reward_track/summary
     */
    public function summary(GetSummaryRequest $request, GetSummaryUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => SummaryResponse::fromResult($useCase->handle($request->getMstRewardTrackId())));
    }

    /**
     * マイルストーンの報酬を受け取る
     *
     * POST /reward_track/receive
     */
    public function receive(ReceiveMilestoneRequest $request, ReceiveMilestoneUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => ReceiveResponse::fromResult($useCase->handle(
            $request->getMstRewardTrackMilestoneId(),
            $request->getMstRewardTrackLineId(),
        )));
    }
}
