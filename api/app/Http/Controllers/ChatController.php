<?php

namespace App\Http\Controllers;

use App\Domain\Chat\UseCases\ChangeRoleUseCase;
use App\Domain\Chat\UseCases\CreateGroupUseCase;
use App\Domain\Chat\UseCases\DeleteMessageUseCase;
use App\Domain\Chat\UseCases\FriendRoomUseCase;
use App\Domain\Chat\UseCases\GroupMembersUseCase;
use App\Domain\Chat\UseCases\GuildRoomUseCase;
use App\Domain\Chat\UseCases\InviteUseCase;
use App\Domain\Chat\UseCases\KickUseCase;
use App\Domain\Chat\UseCases\LeaveGroupUseCase;
use App\Domain\Chat\UseCases\MessagesUseCase;
use App\Domain\Chat\UseCases\RoomsUseCase;
use App\Domain\Chat\UseCases\SendMessageUseCase;
use App\Http\Requests\Chat\ChangeRoleRequest;
use App\Http\Requests\Chat\CreateGroupRequest;
use App\Http\Requests\Chat\DeleteMessageRequest;
use App\Http\Requests\Chat\FriendRoomRequest;
use App\Http\Requests\Chat\GuildRoomRequest;
use App\Http\Requests\Chat\MessagesRequest;
use App\Http\Requests\Chat\RoomMemberRequest;
use App\Http\Requests\Chat\RoomRequest;
use App\Http\Requests\Chat\RoomsRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Responses\Chat\ChangeRoleResponse;
use App\Http\Responses\Chat\CreateGroupResponse;
use App\Http\Responses\Chat\DeleteMessageResponse;
use App\Http\Responses\Chat\FriendRoomResponse;
use App\Http\Responses\Chat\GroupMembersResponse;
use App\Http\Responses\Chat\GuildRoomResponse;
use App\Http\Responses\Chat\InviteResponse;
use App\Http\Responses\Chat\KickResponse;
use App\Http\Responses\Chat\LeaveGroupResponse;
use App\Http\Responses\Chat\MessagesResponse;
use App\Http\Responses\Chat\RoomsResponse;
use App\Http\Responses\Chat\SendResponse;
use Illuminate\Http\JsonResponse;

/**
 * ChatController
 *
 * チャット（フレンドDM・ギルド・グループ）のAPIエンドポイント
 *
 * 実行者は常に認証トークンから解決する。リクエストに含まれる
 * sys_player_id は「相手」を指す。
 */
class ChatController extends _BaseController
{
    /**
     * フレンドDMのルームを取得（無ければ作成）
     *
     * POST /chat/friend/room
     */
    public function friendRoom(FriendRoomRequest $request, FriendRoomUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => FriendRoomResponse::fromResult($useCase->handle($sysPlayerId, $request->getSysPlayerId())));
    }

    /**
     * ギルドチャットのルームを取得（無ければ作成）
     *
     * POST /chat/guild/room
     */
    public function guildRoom(GuildRoomRequest $request, GuildRoomUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => GuildRoomResponse::fromResult($useCase->handle($sysPlayerId)));
    }

    /**
     * 参加中のルーム一覧
     *
     * GET /chat/room/list
     */
    public function rooms(RoomsRequest $request, RoomsUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => RoomsResponse::fromResult($useCase->handle($sysPlayerId)));
    }

    /**
     * メッセージ履歴の取得
     *
     * GET /chat/message/list
     */
    public function messages(MessagesRequest $request, MessagesUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => MessagesResponse::fromResult($useCase->handle(
            $sysPlayerId,
            $request->getSysChatRoomId(),
            $request->getLimit(),
            $request->getCursor(),
        )));
    }

    /**
     * メッセージ送信
     *
     * POST /chat/message/send
     */
    public function send(SendMessageRequest $request, SendMessageUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => SendResponse::fromResult($useCase->handle(
            $sysPlayerId,
            $request->getSysChatRoomId(),
            $request->getBody(),
        )));
    }

    /**
     * 自分のメッセージを削除
     *
     * POST /chat/message/delete
     */
    public function deleteMessage(DeleteMessageRequest $request, DeleteMessageUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(function () use ($useCase, $sysPlayerId, $request) {
            $useCase->handle($sysPlayerId, $request->getSysChatMessageId());

            return new DeleteMessageResponse;
        });
    }

    /**
     * グループチャットを作成
     *
     * POST /chat/group/create
     */
    public function createGroup(CreateGroupRequest $request, CreateGroupUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => CreateGroupResponse::fromResult($useCase->handle($sysPlayerId, $request->getName())));
    }

    /**
     * グループチャットへ招待
     *
     * POST /chat/group/invite
     */
    public function invite(RoomMemberRequest $request, InviteUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => InviteResponse::fromResult($useCase->handle(
            $sysPlayerId,
            $request->getSysChatRoomId(),
            $request->getSysPlayerId(),
        )));
    }

    /**
     * グループチャットからキック
     *
     * POST /chat/group/kick
     */
    public function kick(RoomMemberRequest $request, KickUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(function () use ($useCase, $sysPlayerId, $request) {
            $useCase->handle(
                $sysPlayerId,
                $request->getSysChatRoomId(),
                $request->getSysPlayerId(),
            );

            return new KickResponse;
        });
    }

    /**
     * グループチャットを退室
     *
     * POST /chat/group/leave
     */
    public function leaveGroup(RoomRequest $request, LeaveGroupUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(function () use ($useCase, $sysPlayerId, $request) {
            $useCase->handle($sysPlayerId, $request->getSysChatRoomId());

            return new LeaveGroupResponse;
        });
    }

    /**
     * グループチャットのロールを変更
     *
     * POST /chat/group/role
     */
    public function changeRole(ChangeRoleRequest $request, ChangeRoleUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(function () use ($useCase, $sysPlayerId, $request) {
            $useCase->handle(
                $sysPlayerId,
                $request->getSysChatRoomId(),
                $request->getSysPlayerId(),
                $request->getRole(),
            );

            return new ChangeRoleResponse;
        });
    }

    /**
     * グループチャットのメンバー一覧
     *
     * GET /chat/group/member/list
     */
    public function groupMembers(RoomRequest $request, GroupMembersUseCase $useCase): JsonResponse
    {
        $sysPlayerId = $this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId());

        return $this->execute(fn () => GroupMembersResponse::fromResult($useCase->handle($sysPlayerId, $request->getSysChatRoomId())));
    }
}
