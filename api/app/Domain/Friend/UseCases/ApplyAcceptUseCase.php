<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Friend\Support\FriendExceptionTranslator;
use App\Exceptions\GameException;
use App\Http\Responses\Friend\ApplyAcceptResponse;
use NexusFriend\Services\FriendService;

/**
 * ApplyAcceptUseCase
 *
 * フレンド申請承認ユースケース
 *
 * 申請の状態チェックはパッケージのFriendServiceが持つ。
 * ここではエラーコードの翻訳とレスポンスの組み立てだけを行う。
 */
class ApplyAcceptUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly FriendService $friendService,
    ) {}

    /**
     * フレンド申請承認処理を実行
     *
     * @param  int  $sysPlayerId  承認者のプレイヤーID（受信者）
     * @param  int  $sysFriendApplyId  フレンド申請ID
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $sysFriendApplyId): ApplyAcceptResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $sysFriendApplyId) {
            $friendApply = FriendExceptionTranslator::translate(
                fn () => $this->friendService->acceptApply($sysFriendApplyId, $sysPlayerId)
            );

            return ApplyAcceptResponse::fromDto($friendApply);
        });
    }
}
