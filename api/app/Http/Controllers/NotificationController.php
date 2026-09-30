<?php

namespace App\Http\Controllers;

use App\Domain\Notification\UseCases\ListUseCase;
use App\Domain\Notification\UseCases\ReadAllUseCase;
use App\Domain\Notification\UseCases\ReadUseCase;
use App\Http\Requests\Notification\ListRequest;
use App\Http\Requests\Notification\ReadAllRequest;
use App\Http\Requests\Notification\ReadRequest;
use Illuminate\Http\JsonResponse;

/**
 * NotificationController
 *
 * ゲーム内通知のAPIエンドポイント
 */
class NotificationController extends _BaseController
{
    /**
     * 通知一覧取得
     *
     * GET /notification/list
     */
    public function list(ListRequest $request, ListUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $request->onlyUnread()));
    }

    /**
     * 通知を既読にする
     *
     * POST /notification/read
     */
    public function read(ReadRequest $request, ReadUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $request->getTrxNotificationId()));
    }

    /**
     * 通知を全件既読にする
     *
     * POST /notification/read_all
     */
    public function readAll(ReadAllRequest $request, ReadAllUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => $useCase->exec($sysPlayerId));
    }
}
