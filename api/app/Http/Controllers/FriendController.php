<?php

namespace App\Http\Controllers;

use App\Domain\Friend\UseCases\ApplyAcceptUseCase;
use App\Domain\Friend\UseCases\ApplyListUseCase;
use App\Domain\Friend\UseCases\ApplyRejectUseCase;
use App\Domain\Friend\UseCases\ApplySendUseCase;
use App\Domain\Friend\UseCases\DeleteUseCase;
use App\Domain\Friend\UseCases\ListUseCase;
use App\Http\Requests\Friend\ApplyAcceptRequest;
use App\Http\Requests\Friend\ApplyListRequest;
use App\Http\Requests\Friend\ApplyRejectRequest;
use App\Http\Requests\Friend\ApplySendRequest;
use App\Http\Requests\Friend\DeleteRequest;
use App\Http\Requests\Friend\ListRequest;
use App\Http\Responses\Friend\ApplyAcceptResponse;
use App\Http\Responses\Friend\ApplyListResponse;
use App\Http\Responses\Friend\ApplyRejectResponse;
use App\Http\Responses\Friend\ApplySendResponse;
use App\Http\Responses\Friend\DeleteResponse;
use App\Http\Responses\Friend\ListResponse;
use Illuminate\Http\JsonResponse;

class FriendController extends _BaseController
{
    /**
     * フレンド申請送信API
     *
     * my_idを受け取り、フレンド申請を作成する
     */
    public function applySend(ApplySendRequest $request, ApplySendUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        // リクエストパラメータを取得
        $targetMyId = $request->getMyId();

        return $this->execute(fn () => ApplySendResponse::fromDto($useCase->exec($sysPlayerId, $targetMyId)));
    }

    /**
     * フレンド申請承認API
     *
     * sys_friend_apply_idを受け取り、フレンド申請を承認する
     */
    public function applyAccept(ApplyAcceptRequest $request, ApplyAcceptUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        // リクエストパラメータを取得
        $sysFriendApplyId = $request->getSysFriendApplyId();

        return $this->execute(fn () => ApplyAcceptResponse::fromDto($useCase->exec($sysPlayerId, $sysFriendApplyId)));
    }

    /**
     * フレンド申請却下API
     *
     * sys_friend_apply_idを受け取り、フレンド申請を却下する
     */
    public function applyReject(ApplyRejectRequest $request, ApplyRejectUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        // リクエストパラメータを取得
        $sysFriendApplyId = $request->getSysFriendApplyId();

        return $this->execute(fn () => ApplyRejectResponse::fromDto($useCase->exec($sysPlayerId, $sysFriendApplyId)));
    }

    /**
     * フレンド申請リスト取得API
     *
     * 自分が送信または受信したフレンド申請一覧を取得する（status=Applied）
     */
    public function applyList(ApplyListRequest $request, ApplyListUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => ApplyListResponse::fromCollection($useCase->exec($sysPlayerId)));
    }

    /**
     * フレンドリスト取得API
     *
     * 承認済みのフレンド一覧を取得する（status=Accepted）
     */
    public function list(ListRequest $request, ListUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        // 自分のsys_player_idも渡して、相手のmy_idを取得できるようにする
        return $this->execute(fn () => ListResponse::fromCollection($useCase->exec($sysPlayerId), $sysPlayerId));
    }

    /**
     * フレンド削除API
     *
     * my_idを受け取り、フレンド関係を削除する
     */
    public function delete(DeleteRequest $request, DeleteUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        // リクエストパラメータを取得
        $targetMyId = $request->getMyId();

        return $this->execute(fn () => DeleteResponse::success($useCase->exec($sysPlayerId, $targetMyId)));
    }
}
