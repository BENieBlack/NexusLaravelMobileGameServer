<?php

namespace App\Domain\Login\Services;

use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Services\_BaseLoginBonusService;
use NexusResourceDelivery\Services\ResourceDeliveryService;

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
    protected function getLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array
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
    protected function getBonusContents(array $bonusData, int $currentDay): CustomCollection
    {
        // 指定日数の報酬内容を取得
        $contents = $this->bonusRepository->selectContentsByLoginBonusIdAndDay(
            $bonusData['id'],
            $currentDay
        );

        // stdClassに変換して返す
        return (new CustomCollection($contents))->map(fn ($content) => (object) $content);
    }

    /**
     * {@inheritDoc}
     */
    protected function recordHistory(
        int $sysPlayerId,
        array $bonusData,
        int $currentDay,
        CustomCollection $contents,
        string $connectionName
    ): void {
        $receivedDate = $this->getGameDayStart()->format('Y-m-d H:i:s');

        foreach ($contents as $content) {
            $this->historyRepository->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_login_bonus_id' => $bonusData['id'],
                'absent_days' => null,
                'received_date' => $receivedDate,
                'reward_type' => $content->content_type,
                'reward_id' => $content->content_id,
                'reward_amount' => $content->content_quantity * $content->amount,
                'is_paid' => $content->is_paid ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $connectionName);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getLastReceivedDay(int $sysPlayerId, string $connectionName): ?int
    {
        $lastHistory = $this->historyRepository->selectLatestByPlayerId($sysPlayerId, $connectionName);
        if ($lastHistory === null) {
            return null;
        }

        // ログインボーナスIDから日数を取得（例: login_bonus_day_3 -> 3）
        $bonusId = $lastHistory['mst_login_bonus_id'] ?? '';
        if (preg_match('/_day_(\d+)$/', $bonusId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getLoopDays(int $sysPlayerId): ?int
    {
        $loginBonus = $this->bonusRepository->selectActiveDailyBonus();

        return $loginBonus['loop_days'] ?? null;
    }
}
