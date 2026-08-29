<?php

namespace App\Domain\Login\Services;

use Carbon\CarbonImmutable;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Services\_BaseLoginBonusService;
use NexusResourceDelivery\Services\ResourceDeliveryService;

/**
 * ComeBackLoginBonusService (Domain層)
 *
 * カムバックログインボーナスの配布処理を担当するサービス
 * _BaseLoginBonusServiceを継承し、差分のみオーバーライド
 *
 * 特性（通常ログインボーナスとの差分）:
 * - N日以上ログインしていないユーザーがログインすると発火
 * - 設定期間で終了する（ループしない） ← shouldLoop()をオーバーライド
 * - 1日ログインしないとその日の分はスキップされる ← shouldSkipOnAbsence()をオーバーライド
 */
class ComeBackLoginBonusService extends _BaseLoginBonusService
{
    private ?string $currentConnectionName = null;

    public function __construct(
        ResourceDeliveryService $resourceDeliveryService,
        private readonly LoginBonusRepositoryInterface $bonusRepository,
        private readonly LoginBonusHistoryRepositoryInterface $historyRepository,
    ) {
        parent::__construct($resourceDeliveryService);
    }

    /**
     * {@inheritDoc}
     */
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool
    {
        if ($lastLoginAt === null) {
            return false; // 初回ログインは対象外
        }

        $absentDays = $this->calculateAbsentDays($lastLoginAt);

        // 有効なカムバックボーナス設定があるかチェック
        $bonus = $this->bonusRepository->selectEligibleComebackBonus($absentDays);

        return $bonus !== null;
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
     * カムバック開始日からの経過日数を計算（スキップあり）
     */
    protected function calculateCurrentDay(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): int
    {
        // connectionNameを保存（findLoginBonusDataで使用）
        $this->currentConnectionName = $connectionName;

        // カムバックボーナス初回受け取り日を取得
        $firstComebackHistory = $this->historyRepository->selectFirstComebackByPlayerId($sysPlayerId, $connectionName);

        if ($firstComebackHistory === null) {
            // 初回カムバック = 1日目
            return 1;
        }

        $firstReceivedDate = CarbonImmutable::parse($firstComebackHistory['received_date']);
        $currentGameDayStart = $this->calcGameDayStart();

        // カムバック開始日からの経過日数（スキップあり）
        $daysSinceStart = $firstReceivedDate->diffInDays($currentGameDayStart, false) + 1;

        return max(1, $daysSinceStart);
    }

    /**
     * {@inheritDoc}
     */
    protected function findLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array
    {
        $absentDays = $this->calculateAbsentDays($lastLoginAt);

        // 優先度順でカムバックボーナスを取得
        $bonus = $this->bonusRepository->selectEligibleComebackBonus($absentDays);

        if ($bonus === null) {
            return null;
        }

        // 有効期間チェック
        if (isset($bonus['valid_days']) && $currentDay > $bonus['valid_days']) {
            return null;
        }

        // 今日すでに受け取り済みかチェック
        if ($this->hasReceivedToday($sysPlayerId, $bonus['id'], $this->currentConnectionName)) {
            return null;
        }

        // absent_daysを追加
        $bonus['absent_days'] = $absentDays;

        return $bonus;
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
        $receivedDate = $this->calcGameDayStart()->format('Y-m-d H:i:s');

        foreach ($contents as $content) {
            $this->historyRepository->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_login_bonus_id' => $bonusData['id'],
                'absent_days' => $bonusData['absent_days'],
                'received_date' => $receivedDate,
                'reward_type' => $content->content_type,
                'reward_mst_id' => $content->content_mst_id,
                'reward_amount' => $content->content_quantity * $content->amount,
                'is_paid' => $content->is_paid ?? false,
            ], $connectionName);
        }
    }

    /**
     * 休眠日数を計算
     */
    private function calculateAbsentDays(?string $lastLoginAt): int
    {
        if ($lastLoginAt === null) {
            return 0;
        }

        $lastLogin = CarbonImmutable::parse($lastLoginAt);
        $now = ClockUtility::now();

        return (int) $lastLogin->diffInDays($now, false);
    }

    /**
     * 今日すでに受け取り済みかチェック
     */
    private function hasReceivedToday(int $sysPlayerId, string $bonusId, string $connectionName): bool
    {
        $receivedDate = $this->calcGameDayStart()->format('Y-m-d H:i:s');

        $history = $this->historyRepository->selectByPlayerAndBonusAndDate(
            $sysPlayerId,
            $bonusId,
            $receivedDate,
            $connectionName
        );

        return $history !== null;
    }
}
