<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\ApplySendResponse;
use App\Models\Sys\SysFriendApply;
use App\Repositories\Sys\SysFriendApplyRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Traits\UseCaseTrait;

/**
 * ApplySendUseCase
 * 
 * フレンド申請送信ユースケース
 */
class ApplySendUseCase extends _BaseUseCase
{
    use UseCaseTrait;

    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
        private readonly SysPlayerRepository $sysPlayerRepository,
    ) {
    }

    /**
     * フレンド申請送信処理を実行
     *
     * @param int $sysPlayerId 申請者のプレイヤーID
     * @param string $targetMyId 申請先のmy_id
     * @return ApplySendResponse
     * @throws GameException
     */
    public function handle(int $sysPlayerId, string $targetMyId): ApplySendResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $targetMyId) {
            // 1. 申請先のプレイヤーをmy_idで検索
            $targetPlayer = $this->sysPlayerRepository->selectByMyId($targetMyId);
            
            if ($targetPlayer === null) {
                throw new GameException(
                    GameErrorCode::TARGET_PLAYER_NOT_FOUND,
                    'Target player not found'
                );
            }

            $receivePlayerId = $targetPlayer->getId();

            // 2. 自分自身への申請をチェック
            if ($sysPlayerId === $receivePlayerId) {
                throw new GameException(
                    GameErrorCode::CANNOT_SEND_FRIEND_REQUEST_TO_SELF,
                    'Cannot send friend request to yourself'
                );
            }

            // 3. 既存の申請をチェック（双方向）
            $existingApply = $this->sysFriendApplyRepository->findByPlayerPair(
                $sysPlayerId,
                $receivePlayerId
            );

            if ($existingApply !== null) {
                // 既に申請が存在する場合
                if ($existingApply->getStatus() === SysFriendApply::STATUS_APPLIED) {
                    throw new GameException(
                        GameErrorCode::FRIEND_REQUEST_ALREADY_EXISTS,
                        'Friend request already exists'
                    );
                }
                
                // 既にフレンドの場合
                if ($existingApply->getStatus() === SysFriendApply::STATUS_ACCEPTED) {
                    throw new GameException(
                        GameErrorCode::FRIEND_ALREADY_EXISTS,
                        'Already friends'
                    );
                }
            }

            // 4. 新規フレンド申請を作成
            $sysFriendApply = $this->sysFriendApplyRepository->createApply(
                $sysPlayerId,
                $receivePlayerId
            );

            // 5. レスポンスを返す
            return ApplySendResponse::fromModel($sysFriendApply);
        });
    }
}
