<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Friend\ApplyListResponse;
use App\Repositories\Sys\SysFriendApplyRepository;

/**
 * ApplyListUseCase
 * 
 * フレンド申請リスト取得ユースケース
 */
class ApplyListUseCase extends _BaseUseCase
{

    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
    ) {
    }

    /**
     * フレンド申請リスト取得処理を実行
     * 
     * sender_sys_player_idまたはreceiver_sys_player_idが自分で、
     * statusがAppliedのものを取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @return ApplyListResponse
     */
    public function exec(int $sysPlayerId): ApplyListResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            // 自分が関連するフレンド申請一覧を取得
            $sysFriendApplyCollection = $this->sysFriendApplyRepository->selectAppliesByPlayerId($sysPlayerId);

            // レスポンスを返す
            return ApplyListResponse::fromCollection($sysFriendApplyCollection);
        });
    }
}
