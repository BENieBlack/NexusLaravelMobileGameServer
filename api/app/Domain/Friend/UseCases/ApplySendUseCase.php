<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Friend\Support\FriendExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\ApplySendResponse;
use App\Repositories\Sys\SysPlayerRepository;
use NexusFriend\Services\FriendService;

/**
 * ApplySendUseCase
 *
 * フレンド申請送信ユースケース
 */
class ApplySendUseCase extends _BaseUseCase
{
    public function __construct(
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

            // 2. 申請を作成（重複や自分自身のチェックはFriendServiceが持つ）
            $friendApply = FriendExceptionTranslator::translate(
                fn () => $this->friendService->sendApply($sysPlayerId, $receivePlayerId)
            );

            // 3. レスポンスを返す
            return ApplySendResponse::fromDto($friendApply);
        });
    }
}
