<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Models\Sys\SysFriendApply;
use App\Repositories\Sys\SysFriendApplyRepository;
use Illuminate\Database\Eloquent\Collection;

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
     * @return Collection<int, SysFriendApply>
     */
    public function exec(int $sysPlayerId): Collection
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            // 自分が関連する承認済みフレンド一覧を取得
            return $this->sysFriendApplyRepository->selectAcceptedFriendsByPlayerId($sysPlayerId);
        });
    }
}
