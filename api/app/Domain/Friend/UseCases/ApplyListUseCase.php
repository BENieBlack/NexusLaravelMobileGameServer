<?php

namespace App\Domain\Friend\UseCases;

use App\Domain\_BaseUseCase;
use App\Models\Sys\SysFriendApply;
use App\Repositories\Sys\SysFriendApplyRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * ApplyListUseCase
 *
 * フレンド申請リスト取得ユースケース
 */
class ApplyListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
    ) {}

    /**
     * フレンド申請リスト取得処理を実行
     *
     * sender_sys_player_idまたはreceiver_sys_player_idが自分で、
     * statusがAppliedのものを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return Collection<int, SysFriendApply>
     */
    public function exec(int $sysPlayerId): Collection
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            // 自分が関連するフレンド申請一覧を取得
            return $this->sysFriendApplyRepository->selectAppliesByPlayerId($sysPlayerId);
        });
    }
}
