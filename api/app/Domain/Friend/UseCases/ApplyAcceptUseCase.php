<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\ApplyAcceptResponse;
use App\Models\Sys\SysFriendApply;
use App\Repositories\Sys\SysFriendApplyRepository;

/**
 * ApplyAcceptUseCase
 * 
 * フレンド申請承認ユースケース
 */
class ApplyAcceptUseCase extends _BaseUseCase
{

    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
    ) {
    }

    /**
     * フレンド申請承認処理を実行
     *
     * @param int $sysPlayerId 承認者（受信者）のプレイヤーID
     * @param int $sysFriendApplyId フレンド申請ID
     * @return ApplyAcceptResponse
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $sysFriendApplyId): ApplyAcceptResponse
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
                    GameErrorCode::NOT_AUTHORIZED_TO_ACCEPT,
                    'You are not authorized to accept this friend apply'
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

            // 4. ステータスをAcceptedに変更
            $sysFriendApply->setStatus(SysFriendApply::STATUS_ACCEPTED);
            $this->sysFriendApplyRepository->setModel($sysFriendApply);

            // 5. レスポンスを返す
            return ApplyAcceptResponse::fromModel($sysFriendApply);
        });
    }
}
