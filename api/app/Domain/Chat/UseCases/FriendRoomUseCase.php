<?php

namespace App\Domain\Chat\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Chat\Support\ChatExceptionTranslator;
use App\Http\Responses\Chat\RoomResponse;
use NexusChat\Services\ChatService;

/**
 * FriendRoomUseCase
 *
 * フレンドDMのルームを取得（無ければ作成）する
 *
 * フレンド関係の検証はパッケージの責務ではないため、ここで行う想定。
 * 現状はフレンド判定を挟んでいない（相手が誰でもDMを開ける）。
 */
class FriendRoomUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function exec(int $sysPlayerId, int $targetSysPlayerId): RoomResponse
    {
        return $this->executeWithTransaction(
            fn () => ChatExceptionTranslator::translate(
                fn () => new RoomResponse($this->chatService->findOrCreateFriendRoom($sysPlayerId, $targetSysPlayerId))
            )
        );
    }
}
