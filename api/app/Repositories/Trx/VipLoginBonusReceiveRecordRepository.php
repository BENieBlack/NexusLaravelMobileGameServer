<?php

namespace App\Repositories\Trx;

use App\Repositories\Log\LogVipLoginBonusReceiveRepository;
use Nexus\Core\Support\CustomCollection;
use NexusLogin\DataTransferObjects\LoginBonusContent;

/**
 * VipLoginBonusReceiveRecordRepository
 *
 * VIP ログインボーナスの「受け取った」記録を書く、Repository 層の業務メソッド
 *
 * 最新状態（trx_vip_login_bonus の upsert）と受取履歴（log_action_vip_login_bonus_receive の 1 報酬 1 行）は
 * 必ず組で書く。通常の配布（login。VipLoginBonusService）と再受け取り（POST /login_bonus/receive）が
 * 同じ内容を書くことを保証するため、2 か所に同じコードを書かず、ここへ寄せる。
 * どちらも書き込みはキューに積むだけで、コミットは呼び出し側の executeWithTransaction が行う。
 */
class VipLoginBonusReceiveRecordRepository
{
    public function __construct(
        private readonly TrxVipLoginBonusRepository $trxVipLoginBonusRepository,
        private readonly LogVipLoginBonusReceiveRepository $logVipLoginBonusReceiveRepository,
    ) {}

    /**
     * 受け取りを記録する（最新状態 + 報酬ごとの履歴）
     *
     * @param  string  $receivedAt  受け取り日時（Y-m-d H:i:s形式。その日のゲーム日の開始）
     * @param  CustomCollection<int, LoginBonusContent>  $contents  配った報酬
     */
    public function recordReceive(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        int $day,
        int $vipLevel,
        string $receivedAt,
        CustomCollection $contents,
    ): void {
        // 最新状態はtrx、受取履歴はlogへ記録
        $this->trxVipLoginBonusRepository->upsertLatest(
            sysPlayerId: $sysPlayerId,
            mstVipLoginBonusId: $mstVipLoginBonusId,
            day: $day,
            vipLevel: $vipLevel,
            receivedAt: $receivedAt,
        );

        foreach ($contents as $content) {
            $this->logVipLoginBonusReceiveRepository->insertReceive(
                sysPlayerId: $sysPlayerId,
                mstVipLoginBonusId: $mstVipLoginBonusId,
                day: $day,
                vipLevel: $vipLevel,
                receivedAt: $receivedAt,
                rewardType: $content->getContentType(),
                rewardMstId: $content->getContentMstId(),
                rewardAmount: $content->getTotalQuantity(),
                isPaid: $content->getIsPaid(),
            );
        }
    }
}
