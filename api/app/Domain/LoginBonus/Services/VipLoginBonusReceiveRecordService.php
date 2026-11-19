<?php

namespace App\Domain\LoginBonus\Services;

use App\Domain\LoginBonus\Contracts\VipLoginBonusReceiveRecorderInterface;
use App\Repositories\Log\LogVipLoginBonusReceiveRepository;
use App\Repositories\Trx\TrxVipLoginBonusRepository;
use Nexus\Core\Support\CustomCollection;

/**
 * VipLoginBonusReceiveRecordService
 *
 * VIP ログインボーナスの「受け取った」記録を書く Service
 *
 * 最新状態（trx_vip_login_bonus の upsert）と受取履歴（log_action_vip_login_bonus_receive の 1 報酬 1 行）は
 * 必ず組で書く。片方だけ書くと、最新状態の vip_level・day と履歴が食い違い、再受け取りの判定（R4 ②）や
 * 集計が狂うため。通常の配布（login。Login\Services\VipLoginBonusService）と再受け取り
 * （POST /login_bonus/receive。ReceiveUseCase）の 2 つの経路が同じ内容を書くことを保証するため、
 * 2 か所に同じコードを書かず、ここに 1 つだけ置く（Login へは Contracts の interface で公開）。
 * どちらも書き込みはキューに積むだけで、コミットは呼び出し側の executeWithTransaction が行う。
 * 個々のテーブルの読み書きは、単体の Repository（Trx・Log）に任せる。
 */
class VipLoginBonusReceiveRecordService implements VipLoginBonusReceiveRecorderInterface
{
    public function __construct(
        private readonly TrxVipLoginBonusRepository $trxVipLoginBonusRepository,
        private readonly LogVipLoginBonusReceiveRepository $logVipLoginBonusReceiveRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function recordReceive(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        int $day,
        int $vipLevel,
        string $receivedAt,
        CustomCollection $contents,
    ): void {
        // 最新状態は trx、受取履歴は log へ記録
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
