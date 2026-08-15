<?php

namespace App\Http\Controllers;

use App\Domain\Guild\UseCases\GuildApplyAcceptUseCase;
use App\Domain\Guild\UseCases\GuildApplyListUseCase;
use App\Domain\Guild\UseCases\GuildApplyRejectUseCase;
use App\Domain\Guild\UseCases\GuildApplySendUseCase;
use App\Domain\Guild\UseCases\GuildCreateUseCase;
use App\Domain\Guild\UseCases\GuildDetailUseCase;
use App\Domain\Guild\UseCases\GuildLeaveUseCase;
use App\Domain\Guild\UseCases\GuildListUseCase;
use App\Domain\Guild\UseCases\GuildMemberListUseCase;
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
    public function list(GuildListUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => $useCase->exec());
    }

    /**
     * ギルド詳細取得API
     */
    public function detail(Request $request, GuildDetailUseCase $useCase): JsonResponse
    {
        $guildId = (int) $request->input('guild_id');

        return $this->execute(fn () => $useCase->exec($guildId));
    }

    /**
     * ギルド作成API
     */
    public function create(CreateRequest $request, GuildCreateUseCase $useCase): JsonResponse
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
    public function applySend(ApplySendRequest $request, GuildApplySendUseCase $useCase): JsonResponse
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
    public function applyAccept(ApplyAcceptRequest $request, GuildApplyAcceptUseCase $useCase): JsonResponse
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
    public function applyReject(ApplyRejectRequest $request, GuildApplyRejectUseCase $useCase): JsonResponse
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
    public function applyList(Request $request, GuildApplyListUseCase $useCase): JsonResponse
    {
        $guildId = (int) $request->input('guild_id');

        return $this->execute(fn () => $useCase->exec($guildId));
    }

    /**
     * ギルドメンバー一覧取得API
     */
    public function memberList(Request $request, GuildMemberListUseCase $useCase): JsonResponse
    {
        $guildId = (int) $request->input('guild_id');

        return $this->execute(fn () => $useCase->exec($guildId));
    }

    /**
     * ギルド脱退API
     */
    public function leave(LeaveRequest $request, GuildLeaveUseCase $useCase): JsonResponse
    {
        // 認証情報を取得
        $sysPlayerId = $request->resolveAuthenticatedPlayerId();

        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        $useCase->exec($sysPlayerId);

        return $this->success([]);
    }
}
