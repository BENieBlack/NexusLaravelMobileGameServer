<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Friend\Support\FriendExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\DeleteResponse;
use App\Repositories\Sys\SysPlayerRepository;
use NexusFriend\Services\FriendService;

/**
 * DeleteUseCase
 *
 * フレンド削除ユースケース
 */
class DeleteUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly FriendService $friendService,
        private readonly SysPlayerRepository $sysPlayerRepository,
    ) {}

    /**
     * フレンド削除処理を実行
     *
     * @param  int  $sysPlayerId  削除実行者のプレイヤーID
     * @param  string  $targetMyId  削除対象のフレンドのmy_id
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, string $targetMyId): DeleteResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $targetMyId) {
            // 1. 削除対象のプレイヤーをmy_idで検索
            $targetPlayer = $this->sysPlayerRepository->selectByMyId($targetMyId);

            if ($targetPlayer === null) {
                throw new GameException(
                    GameErrorCode::TARGET_PLAYER_NOT_FOUND,
                    'Target player not found'
                );
            }

            $targetPlayerId = $targetPlayer->getId();

            // 2. フレンド関係を削除（自分自身のチェックはFriendServiceが持つ）
            FriendExceptionTranslator::translateForDelete(
                fn () => $this->friendService->deleteFriend($sysPlayerId, $targetPlayerId)
            );

            // 3. レスポンスを返す
            return DeleteResponse::success($targetPlayer->getMyId());
        });
    }
}
