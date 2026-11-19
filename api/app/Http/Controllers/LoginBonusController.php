<?php

namespace App\Http\Controllers;

use App\Domain\LoginBonus\UseCases\ListUseCase;
use App\Domain\LoginBonus\UseCases\ReceiveUseCase;
use App\Http\Requests\LoginBonus\ListRequest;
use App\Http\Requests\LoginBonus\ReceiveRequest;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * LoginBonusController
 *
 * ログインボーナスのカレンダー表示と、VIP ログインボーナスの再受け取りのAPIエンドポイント
 */
class LoginBonusController extends _BaseController
{
    public function __construct(
        private readonly ListUseCase $listUseCase,
        private readonly ReceiveUseCase $receiveUseCase,
        private readonly ApiSession $apiSession,
    ) {}

    /**
     * カレンダー（通常・VIP の 2 つの段）の最新の状態
     *
     * POST /api/login_bonus/list
     */
    public function list(ListRequest $request): JsonResponse
    {
        return $this->execute(fn () => $this->listUseCase->handle($this->apiSession->getSysPlayerId()));
    }

    /**
     * VIP ログインボーナスを再受け取りする（レベルが上がった日の差額）
     *
     * POST /api/login_bonus/receive
     */
    public function receive(ReceiveRequest $request): JsonResponse
    {
        return $this->execute(fn () => $this->receiveUseCase->handle(
            sysPlayerId: $this->apiSession->getSysPlayerId(),
            mstVipLoginBonusId: $request->getMstVipLoginBonusId(),
        ));
    }
}
