<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Friend\ListResponse;
use App\Repositories\Sys\SysFriendApplyRepository;

/**
 * ListUseCase
 *
 * フレンドリスト取得ユースケース
 * status=Acceptedのフレンド関係を取得
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
    ) {}

    /**
     * フレンドリスト取得処理を実行
     *
     * sender_sys_player_idまたはreceiver_sys_player_idが自分で、
     * statusがAcceptedのものを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function exec(int $sysPlayerId): ListResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            // 自分が関連する承認済みフレンド一覧を取得
            $sysFriendApplyCollection = $this->sysFriendApplyRepository->selectAcceptedFriendsByPlayerId($sysPlayerId);

            // レスポンスを返す（自分のsys_player_idを渡して、相手のmy_idを取得できるようにする）
            return ListResponse::fromCollection($sysFriendApplyCollection, $sysPlayerId);
        });
    }
}
