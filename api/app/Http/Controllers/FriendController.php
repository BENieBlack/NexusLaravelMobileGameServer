<?php

namespace App\Http\Controllers;

use App\Domain\Friend\UseCases\ApplyAcceptUseCase;
use App\Domain\Friend\UseCases\ApplyListUseCase;
use App\Domain\Friend\UseCases\ApplyRejectUseCase;
use App\Domain\Friend\UseCases\ApplySendUseCase;
use App\Domain\Friend\UseCases\DeleteUseCase;
use App\Domain\Friend\UseCases\ListUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Requests\Friend\ApplyAcceptRequest;
use App\Http\Requests\Friend\ApplyListRequest;
use App\Http\Requests\Friend\ApplyRejectRequest;
use App\Http\Requests\Friend\ApplySendRequest;
use App\Http\Requests\Friend\DeleteRequest;
use App\Http\Requests\Friend\ListRequest;
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
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        // リクエストパラメータを取得
        $targetMyId = $request->getMyId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $targetMyId));
    }

    /**
     * フレンド申請承認API
     *
     * sys_friend_apply_idを受け取り、フレンド申請を承認する
     */
    public function applyAccept(ApplyAcceptRequest $request, ApplyAcceptUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        // リクエストパラメータを取得
        $sysFriendApplyId = $request->getSysFriendApplyId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $sysFriendApplyId));
    }

    /**
     * フレンド申請却下API
     *
     * sys_friend_apply_idを受け取り、フレンド申請を却下する
     */
    public function applyReject(ApplyRejectRequest $request, ApplyRejectUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        // リクエストパラメータを取得
        $sysFriendApplyId = $request->getSysFriendApplyId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $sysFriendApplyId));
    }

    /**
     * フレンド申請リスト取得API
     *
     * 自分が送信または受信したフレンド申請一覧を取得する（status=Applied）
     */
    public function applyList(ApplyListRequest $request, ApplyListUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        return $this->execute(fn () => $useCase->exec($sysPlayerId));
    }

    /**
     * フレンドリスト取得API
     *
     * 承認済みのフレンド一覧を取得する（status=Accepted）
     */
    public function list(ListRequest $request, ListUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        return $this->execute(fn () => $useCase->exec($sysPlayerId));
    }

    /**
     * フレンド削除API
     *
     * my_idを受け取り、フレンド関係を削除する
     */
    public function delete(DeleteRequest $request, DeleteUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        // リクエストパラメータを取得
        $targetMyId = $request->getMyId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $targetMyId));
    }
}
