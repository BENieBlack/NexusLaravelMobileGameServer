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
 * LoginBonusService (Domain層)
 *
 * 通常ログインボーナスの配布処理を担当するサービス
 * _BaseLoginBonusServiceを継承（デフォルト動作そのまま使用）
 *
 * 特性:
 * - 毎日日跨ぎ後にもらえる
 * - 設定日数でループする（継承元のデフォルト）
 * - 1日ログインしなくてもスキップしない（継承元のデフォルト）
 */
class LoginBonusService extends _BaseLoginBonusService
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
     * 今日初回ログインかチェック
     */
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool
    {
        $currentTimeString = ClockUtility::nowToString();

        // DAY_START_TIMEを考慮して、今日初回ログインかをチェック
        return $lastLoginAt === null || ! ClockUtility::isSameGameDay($currentTimeString, $lastLoginAt);
    }

    /**
     * {@inheritDoc}
     */
    protected function findLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array
    {
        // mst_login_bonusは日数ごとに1レコード持つため、該当日の設定を取得する
        $loginBonus = $this->bonusRepository->selectActiveByDay($currentDay);

        if ($loginBonus === null) {
            return null;
        }

        return $loginBonus;
    }

    /**
     * {@inheritDoc}
     */
    protected function findBonusContents(array $bonusData, int $currentDay): CustomCollection
    {
        // 指定日数の報酬内容を取得
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
                type: MstLoginBonus::TYPE_DAILY,
                mstLoginBonusId: $bonusData['id'],
                day: $currentDay,
                absentDays: null,
                receivedDate: $receivedDate,
                rewardType: $content->getContentType(),
                rewardMstId: $content->getContentMstId(),
                rewardAmount: $content->getTotalQuantity(),
                isPaid: $content->getIsPaid(),
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function findLastReceivedDay(int $sysPlayerId): ?int
    {
        return $this->historyRepository->selectLatestByPlayerId($sysPlayerId)?->getDay();
    }

    /**
     * {@inheritDoc}
     */
    protected function findLoopDays(int $sysPlayerId): ?int
    {
        $loginBonus = $this->bonusRepository->selectActiveDailyBonus();

        return $loginBonus['loop_days'] ?? null;
    }
}
