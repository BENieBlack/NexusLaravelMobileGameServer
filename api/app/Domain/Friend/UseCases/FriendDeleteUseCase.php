<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\DeleteResponse;
use App\Repositories\Sys\SysFriendApplyRepository;
use App\Repositories\Sys\SysPlayerRepository;

/**
 * FriendDeleteUseCase
 *
 * フレンド削除ユースケース
 */
class FriendDeleteUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
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

            // 2. 自分自身の削除をチェック
            if ($sysPlayerId === $targetPlayerId) {
                throw new GameException(
                    GameErrorCode::CANNOT_DELETE_SELF,
                    'Cannot delete yourself'
                );
            }

            // 3. フレンド関係を削除
            $deletedRelation = $this->sysFriendApplyRepository->deleteFriendRelation(
                $sysPlayerId,
                $targetPlayerId
            );

            if ($deletedRelation === null) {
                throw new GameException(
                    GameErrorCode::FRIEND_NOT_FOUND,
                    'Friend relation not found'
                );
            }

            // 4. レスポンスを返す
            return DeleteResponse::success($targetPlayer->getMyId());
        });
    }
}
