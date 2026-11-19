<?php

namespace App\Domain\LoginBonus\Contracts;

use App\Models\Trx\TrxVipLoginBonus;
use NexusLogin\DataTransferObjects\LoginBonusHistory;

/**
 * LoginBonusCalendarInterface
 *
 * LoginBonus ドメインの公開 API。Login（HomeUseCase）がカレンダーの状態を組み立てるための読み取り。
 * 戻り値はスカラー・Model・パッケージの DTO だけ（このドメインの Result を他へ出さない）。
 * どれも DB へは書かない。login から呼ぶときは、配布と last_login_at の更新の「後」に呼ぶこと
 * （配布前だと最後に配った日数が古い）。
 */
interface LoginBonusCalendarInterface
{
    /**
     * 有効（かつイベント期間内）な通常ログインボーナスの ID。無ければ null
     */
    public function findNormalBonusId(): ?string;

    /**
     * 通常ログインボーナスの最新の受け取り状態（まだ 1 度も配っていなければ null）
     */
    public function findNormalHistory(int $sysPlayerId): ?LoginBonusHistory;

    /**
     * 今の VIP レベルの有効な VIP ログインボーナスの ID。無ければ null
     */
    public function findVipBonusId(int $sysPlayerId): ?string;

    /**
     * 今の VIP レベル（sys_player.vip_point から算出。受取済みの行の vip_level ではない）
     */
    public function findVipLevel(int $sysPlayerId): int;

    /**
     * VIP ログインボーナスの最新の受け取り状態（まだ 1 度も配っていなければ null）
     */
    public function findVipHistory(int $sysPlayerId): ?TrxVipLoginBonus;

    /**
     * VIP の再受け取りが可能か（要件 R4 ①〜④をすべて満たす。receive の再判定と同じ判定）
     */
    public function canVipReReceive(int $sysPlayerId): bool;

    /**
     * 受取日が今日（今のゲーム日）か。行が無い（null）なら偽
     *
     * 文字列の一致ではなく isSameGameDay で比べる。配布の判定（last_login_at の isSameGameDay）と同じ基準になり、
     * DAY_START_TIME を変えた直後でも食い違わない。
     */
    public function isReceivedToday(?string $receivedAt): bool;
}
