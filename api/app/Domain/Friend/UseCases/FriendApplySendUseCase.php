<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\ApplySendResponse;
use App\Repositories\Sys\SysFriendApplyRepository;
use App\Repositories\Sys\SysPlayerRepository;
use NexusFriend\Services\FriendService;

/**
 * FriendApplySendUseCase
 *
 * フレンド申請送信ユースケース
 */
class FriendApplySendUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly FriendService $friendService,
    ) {}

    /**
     * フレンド申請送信処理を実行
     *
     * @param  int  $sysPlayerId  申請者のプレイヤーID
     * @param  string  $targetMyId  申請先のmy_id
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, string $targetMyId): ApplySendResponse
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

            // 2. バリデーション（FriendServiceを使用）
            try {
                $this->friendService->validateNotSelfApply($sysPlayerId, $receivePlayerId);
                $this->friendService->validateNoDuplicateApply($sysPlayerId, $receivePlayerId);
            } catch (\RuntimeException $e) {
                // パッケージの例外をGameExceptionに変換
                $errorCode = match ($e->getMessage()) {
                    'Cannot send friend request to yourself' => GameErrorCode::CANNOT_SEND_FRIEND_REQUEST_TO_SELF,
                    'Friend request already exists' => GameErrorCode::FRIEND_REQUEST_ALREADY_EXISTS,
                    'Already friends' => GameErrorCode::FRIEND_ALREADY_EXISTS,
                    default => GameErrorCode::FRIEND_REQUEST_ALREADY_EXISTS,
                };
                throw new GameException($errorCode, $e->getMessage());
            }

            // 3. 新規フレンド申請を作成
            $sysFriendApply = $this->sysFriendApplyRepository->insertApply(
                $sysPlayerId,
                $receivePlayerId
            );

            // 4. レスポンスを返す
            return ApplySendResponse::fromModel($sysFriendApply);
        });
    }
}
