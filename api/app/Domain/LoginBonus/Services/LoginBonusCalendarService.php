<?php

namespace App\Domain\LoginBonus\Services;

use App\Domain\LoginBonus\Contracts\LoginBonusCalendarInterface;
use App\Domain\LoginBonus\Support\VipLoginBonusReReceiveResult;
use App\Models\Trx\TrxVipLoginBonus;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\DataTransferObjects\LoginBonusHistory;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;

/**
 * LoginBonusCalendarService
 *
 * カレンダー表示（login・list・receive の応答）に要る「状態」の読み取りを、小さなメソッドに分けて提供する。
 * 状態の組み合わせ（is_granted_now など）と API の形への整形は、呼び出し側の UseCase / Response が行う。
 * DB へは書かない。
 */
class LoginBonusCalendarService implements LoginBonusCalendarInterface
{
    private ?VipLoginBonusReReceiveResult $judged = null;

    private ?int $judgedSysPlayerId = null;

    public function __construct(
        private readonly LoginBonusRepositoryInterface $loginBonusRepository,
        private readonly LoginBonusHistoryRepositoryInterface $loginBonusHistoryRepository,
        private readonly VipLoginBonusHistoryRepositoryInterface $vipHistoryRepository,
        private readonly VipLoginBonusReReceiveService $reReceiveService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findNormalBonusId(): ?string
    {
        // 配布と同じ引き方（イベント期間も見る）で 1 件
        $bonus = $this->loginBonusRepository->selectActiveDailyBonus();

        return $bonus === null ? null : (string) $bonus['id'];
    }

    /**
     * {@inheritDoc}
     */
    public function findNormalHistory(int $sysPlayerId): ?LoginBonusHistory
    {
        // login 後は、同じリクエストで積んだ未保存の更新も見える（queryOrMemory）
        return $this->loginBonusHistoryRepository->selectLatestByPlayerId($sysPlayerId);
    }

    /**
     * {@inheritDoc}
     */
    public function findVipBonusId(int $sysPlayerId): ?string
    {
        $master = $this->judge($sysPlayerId)->master;

        return $master === null ? null : (string) $master['id'];
    }

    /**
     * {@inheritDoc}
     */
    public function findVipLevel(int $sysPlayerId): int
    {
        // VIP レベルは列に持たず、累積 VIP ポイントから算出する（judge の中で算出した値）
        return $this->judge($sysPlayerId)->vipLevel;
    }

    /**
     * {@inheritDoc}
     */
    public function findVipHistory(int $sysPlayerId): ?TrxVipLoginBonus
    {
        return $this->vipHistoryRepository->selectLatestByPlayerId($sysPlayerId);
    }

    /**
     * {@inheritDoc}
     */
    public function canVipReReceive(int $sysPlayerId): bool
    {
        // receive の再判定と同じ関数を使う（ボタンは出ているのに受け取れない、を防ぐ）
        return $this->judge($sysPlayerId)->canReReceive;
    }

    /**
     * {@inheritDoc}
     */
    public function isReceivedToday(?string $receivedAt): bool
    {
        return $receivedAt !== null && ClockUtility::isSameGameDay(ClockUtility::nowToString(), $receivedAt);
    }

    /**
     * 判定（今のレベル・マスター・再受け取り可否）をこのインスタンスの中で 1 回だけ行う
     *
     * VIP の段の ID・レベル・can_re_receive は同じ judge() の結果から取るので、同じ読み取り
     * （mst_vip_login_bonus など）を段の項目ごとに繰り返さない。使うのは、書き込みが済んだ後の
     * 読み取りだけ（login は配布の後、list は読み取りのみ）。リクエストごとに新しいインスタンスで使うこと。
     */
    private function judge(int $sysPlayerId): VipLoginBonusReReceiveResult
    {
        if ($this->judged === null || $this->judgedSysPlayerId !== $sysPlayerId) {
            $this->judged = $this->reReceiveService->judge($sysPlayerId);
            $this->judgedSysPlayerId = $sysPlayerId;
        }

        return $this->judged;
    }
}
