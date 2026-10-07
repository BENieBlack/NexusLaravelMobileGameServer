<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Friend\Support\FriendExceptionTranslator;
use App\Exceptions\GameException;
use NexusFriend\DataTransferObjects\FriendApply;
use NexusFriend\Services\FriendService;

/**
 * ApplyRejectUseCase
 *
 * フレンド申請却下ユースケース
 *
 * 申請の状態チェックはパッケージのFriendServiceが持つ。
 * ここではエラーコードの翻訳だけを行う。
 */
class ApplyRejectUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly FriendService $friendService,
    ) {}

    /**
     * フレンド申請却下処理を実行
     *
     * @param  int  $sysPlayerId  却下者のプレイヤーID（受信者）
     * @param  int  $sysFriendApplyId  フレンド申請ID
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $sysFriendApplyId): FriendApply
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $sysFriendApplyId) {
            // 却下の権限エラーは承認とコードが分かれている
            $friendApply = FriendExceptionTranslator::translateForReject(
                fn () => $this->friendService->rejectApply($sysFriendApplyId, $sysPlayerId)
            );

            return $friendApply;
        });
    }
}
