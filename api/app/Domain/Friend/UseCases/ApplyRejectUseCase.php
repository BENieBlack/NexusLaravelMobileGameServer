<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\ApplyRejectResponse;
use App\Models\Sys\SysFriendApply;
use App\Repositories\Sys\SysFriendApplyRepository;

/**
 * ApplyRejectUseCase
 * 
 * フレンド申請却下ユースケース
 */
class ApplyRejectUseCase extends _BaseUseCase
{

    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
    ) {
    }

    /**
     * フレンド申請却下処理を実行
     *
     * @param int $sysPlayerId 却下者（受信者）のプレイヤーID
     * @param int $sysFriendApplyId フレンド申請ID
     * @return ApplyRejectResponse
     * @throws GameException
     */
    public function handle(int $sysPlayerId, int $sysFriendApplyId): ApplyRejectResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId, $sysFriendApplyId) {
            // 1. フレンド申請をIDで検索
            $sysFriendApply = $this->sysFriendApplyRepository->selectById($sysFriendApplyId);
            
            if ($sysFriendApply === null) {
                throw new GameException(
                    GameErrorCode::FRIEND_APPLY_NOT_FOUND,
                    'Friend apply not found'
                );
            }

            // 2. 受信者が自分かチェック
            if ($sysFriendApply->getReceiverSysPlayerId() !== $sysPlayerId) {
                throw new GameException(
                    GameErrorCode::NOT_AUTHORIZED_TO_REJECT,
                    'You are not authorized to reject this friend apply'
                );
            }

            // 3. ステータスをチェック
            if ($sysFriendApply->getStatus() === SysFriendApply::STATUS_ACCEPTED) {
                throw new GameException(
                    GameErrorCode::FRIEND_APPLY_ALREADY_ACCEPTED,
                    'Friend apply already accepted'
                );
            }

            if ($sysFriendApply->getStatus() === SysFriendApply::STATUS_REJECTED) {
                throw new GameException(
                    GameErrorCode::FRIEND_APPLY_ALREADY_REJECTED,
                    'Friend apply already rejected'
                );
            }

            if ($sysFriendApply->getStatus() === SysFriendApply::STATUS_DELETED) {
                throw new GameException(
                    GameErrorCode::FRIEND_APPLY_ALREADY_DELETED,
                    'Friend apply already deleted'
                );
            }

            // 4. ステータスをRejectedに変更
            $sysFriendApply->setStatus(SysFriendApply::STATUS_REJECTED);
            $this->sysFriendApplyRepository->setModel($sysFriendApply);

            // 5. レスポンスを返す
            return ApplyRejectResponse::fromModel($sysFriendApply);
        });
    }
}
