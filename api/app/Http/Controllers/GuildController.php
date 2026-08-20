<?php

namespace App\Http\Controllers;

use App\Domain\Guild\UseCases\ApplyAcceptUseCase;
use App\Domain\Guild\UseCases\ApplyListUseCase;
use App\Domain\Guild\UseCases\ApplyRejectUseCase;
use App\Domain\Guild\UseCases\ApplySendUseCase;
use App\Domain\Guild\UseCases\CreateUseCase;
use App\Domain\Guild\UseCases\DetailUseCase;
use App\Domain\Guild\UseCases\LeaveUseCase;
use App\Domain\Guild\UseCases\ListUseCase;
use App\Domain\Guild\UseCases\MemberListUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Requests\Guild\ApplyAcceptRequest;
use App\Http\Requests\Guild\ApplyRejectRequest;
use App\Http\Requests\Guild\ApplySendRequest;
use App\Http\Requests\Guild\CreateRequest;
use App\Http\Requests\Guild\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuildController extends _BaseController
{
    /**
     * ギルド一覧取得API
     */
    public function list(ListUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec());
    }

    /**
     * ギルド詳細取得API
     */
    public function detail(Request $request, DetailUseCase $useCase): JsonResponse
    {
        $guildId = (int) $request->input('sys_guild_id');

        return $this->execute(fn () => $useCase->exec($guildId));
    }

    /**
     * ギルド作成API
     */
    public function create(CreateRequest $request, CreateUseCase $useCase): JsonResponse
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
        $name = $request->getName();
        $description = $request->getDescription();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $name, $description));
    }

    /**
     * ギルド加入申請送信API
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
        $guildId = $request->getGuildId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $guildId));
    }

    /**
     * ギルド加入申請承認API
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
        $applyId = $request->getApplyId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $applyId));
    }

    /**
     * ギルド加入申請却下API
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
        $applyId = $request->getApplyId();

        return $this->execute(fn () => $useCase->exec($sysPlayerId, $applyId));
    }

    /**
     * ギルド加入申請一覧取得API
     */
    public function applyList(Request $request, ApplyListUseCase $useCase): JsonResponse
    {
        $guildId = (int) $request->input('sys_guild_id');

        return $this->execute(fn () => $useCase->exec($guildId));
    }

    /**
     * ギルドメンバー一覧取得API
     */
    public function memberList(Request $request, MemberListUseCase $useCase): JsonResponse
    {
        $guildId = (int) $request->input('sys_guild_id');

        return $this->execute(fn () => $useCase->exec($guildId));
    }

    /**
     * ギルド脱退API
     */
    public function leave(LeaveRequest $request, LeaveUseCase $useCase): JsonResponse
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
}
