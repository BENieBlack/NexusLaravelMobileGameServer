<?php

namespace App\Domain\Login\Services;

use App\Models\Mst\MstLoginBonus;
use Nexus\Core\ResourceDelivery\Services\ResourceDeliveryService;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Services\_BaseLoginBonusService;

/**
 * ComeBackLoginBonusService (Domain層)
 *
 * カムバックログインボーナスの配布処理を担当するサービス
 * _BaseLoginBonusServiceを継承し、差分のみオーバーライド
 *
 * 特性（通常ログインボーナスとの差分）:
 * - N日以上ログインしていないユーザーがログインすると期間が始まる
 * - 期間中は毎日受け取れる。休眠日数の条件は期間の初日だけ見る
 * - 設定期間（valid_days）で終了する（ループしない） ← shouldLoop()をオーバーライド
 * - 1日ログインしないとその日の分はスキップされる ← shouldSkipOnAbsence()をオーバーライド
 * - 期間の開始日は保存せず、trx_login_bonus の受取日と日数から逆算する
 */
class ComeBackLoginBonusService extends _BaseLoginBonusService
{
    public function __construct(
        ResourceDeliveryService $resourceDeliveryService,
        private readonly LoginBonusRepositoryInterface $bonusRepository,
        private readonly LoginBonusHistoryRepositoryInterface $historyRepository,
    ) {
        parent::__construct($resourceDeliveryService);
    }

    /**
     * {@inheritDoc}
     *
     * 休眠明けで新しい期間が始まるか、進行中の期間があり、今日まだ受け取っていなければ対象
     */
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool
    {
        $period = $this->resolvePeriod($sysPlayerId, $lastLoginAt);

        return $period !== null && ! $this->hasReceivedToday($sysPlayerId, $period['bonus']['id']);
    }

    /**
     * {@inheritDoc}
     *
     * カムバックボーナスはループしない
     */
    protected function shouldLoop(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * カムバックボーナスはスキップする（毎日ログインが必要）
     */
    protected function shouldSkipOnAbsence(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * カムバック期間の何日目か（ログインしなかった日も数える）
     */
    protected function calculateCurrentDay(int $sysPlayerId, ?string $lastLoginAt): int
    {
        return $this->resolvePeriod($sysPlayerId, $lastLoginAt)['day'] ?? 1;
    }

    /**
     * {@inheritDoc}
     */
    protected function findLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array
    {
        $period = $this->resolvePeriod($sysPlayerId, $lastLoginAt);

        if ($period === null) {
            return null;
        }

        // 今日すでに受け取り済みかチェック
        if ($this->hasReceivedToday($sysPlayerId, $period['bonus']['id'])) {
            return null;
        }

        return [...$period['bonus'], 'absent_days' => $period['absent_days']];
    }

    /**
     * {@inheritDoc}
     */
    protected function findBonusContents(array $bonusData, int $currentDay): CustomCollection
    {
        $contents = $this->bonusRepository->selectContentsByLoginBonusIdAndDay(
            $bonusData['id'],
            $currentDay
        );

        return $this->toContents($contents);
    }

    /**
     * {@inheritDoc}
     */
    protected function recordHistory(
        int $sysPlayerId,
        array $bonusData,
        int $currentDay,
        CustomCollection $contents
    ): void {
        $receivedDate = $this->calcGameDayStart()->format('Y-m-d H:i:s');

        foreach ($contents as $content) {
            $this->historyRepository->insertReceive(
                sysPlayerId: $sysPlayerId,
                type: MstLoginBonus::TYPE_COMEBACK,
                mstLoginBonusId: $bonusData['id'],
                // 期間の何日目かを残し、次回は受取日から期間の開始日を逆算する
                day: $currentDay,
                absentDays: $bonusData['absent_days'],
                receivedDate: $receivedDate,
                rewardType: $content->getContentType(),
                rewardMstId: $content->getContentMstId(),
                rewardAmount: $content->getTotalQuantity(),
                isPaid: $content->getIsPaid(),
            );
        }
    }

    /**
     * 今回のログインで配布するカムバック期間を求める
     *
     * - 休眠日数がボーナスの条件を満たせば、新しい期間の1日目
     * - そうでなければ、前回受け取ったボーナスの期間の続き（開始日からの経過日数）
     * - 期間が無い、ボーナスが無効、有効日数を過ぎた場合はnull
     *
     * @return array{bonus: array<string, mixed>, day: int, absent_days: int}|null
     */
    private function resolvePeriod(int $sysPlayerId, ?string $lastLoginAt): ?array
    {
        if ($lastLoginAt !== null) {
            $absentDays = $this->calculateAbsentDays($lastLoginAt);
            $bonus = $this->bonusRepository->selectEligibleComebackBonus($absentDays);

            if ($bonus !== null) {
                return ['bonus' => $bonus, 'day' => 1, 'absent_days' => $absentDays];
            }
        }

        $history = $this->historyRepository->selectFirstComebackByPlayerId($sysPlayerId);

        if ($history === null) {
            return null;
        }

        $bonus = $this->bonusRepository->selectActiveComebackBonusById($history->getMstLoginBonusId());

        if ($bonus === null) {
            return null;
        }

        // 期間の開始日 = 前回の受取日 - (前回の日数 - 1)
        $periodStart = ClockUtility::parse($history->getReceivedDate())->subDays(max(1, $history->getDay()) - 1);
        $day = (int) $periodStart->diffInDays($this->calcGameDayStart(), false) + 1;

        if (isset($bonus['valid_days']) && $day > $bonus['valid_days']) {
            return null;
        }

        return ['bonus' => $bonus, 'day' => max(1, $day), 'absent_days' => $history->getAbsentDays() ?? 0];
    }

    /**
     * 休眠日数を計算
     */
    private function calculateAbsentDays(?string $lastLoginAt): int
    {
        if ($lastLoginAt === null) {
            return 0;
        }

        $lastLogin = ClockUtility::parse($lastLoginAt);
        $now = ClockUtility::now();

        return (int) $lastLogin->diffInDays($now, false);
    }

    /**
     * 今日すでに受け取り済みかチェック
     */
    private function hasReceivedToday(int $sysPlayerId, string $bonusId): bool
    {
        $receivedDate = $this->calcGameDayStart()->format('Y-m-d H:i:s');

        $history = $this->historyRepository->selectByPlayerAndBonusAndDate(
            $sysPlayerId,
            $bonusId,
            $receivedDate,
        );

        return $history !== null;
    }
}
