<?php

namespace App\Domain\LoginBonus\Contracts;

use Nexus\Core\Support\CustomCollection;
use NexusLogin\DataTransferObjects\LoginBonusContent;

/**
 * VipLoginBonusReceiveRecorderInterface
 *
 * LoginBonus ドメインの公開 API。VIP ログインボーナスの「受け取った」記録を書く。
 * Login（login の通常配布。VipLoginBonusService）が使う。
 */
interface VipLoginBonusReceiveRecorderInterface
{
    /**
     * 受け取りを記録する（最新状態 trx_vip_login_bonus の upsert + 報酬ごとの受取履歴の追加）
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
    ): void;
}
