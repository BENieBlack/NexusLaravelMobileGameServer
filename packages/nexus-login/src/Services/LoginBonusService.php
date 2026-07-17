<?php

namespace NexusLogin\Services;

use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use Carbon\CarbonImmutable;
use NexusUtilities\ClockUtility;
use Illuminate\Support\Collection;

/**
 * LoginBonusService
 *
 * ログインボーナスの配布処理を担当するサービス
 */
class LoginBonusService
{
    public function __construct(
        private readonly ResourceDeliveryService $resourceDeliveryService,
        private readonly LoginBonusRepositoryInterface $bonusRepository,
        private readonly LoginBonusHistoryRepositoryInterface $historyRepository,
    ) {
    }

    /**
     * 今日初回ログインかどうかをチェックし、ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param string|null $lastLoginAt 最終ログイン日時（UTC、文字列形式）
     * @param string $connectionName シャーディングされたDB接続名
     * @param CarbonImmutable|null $now 現在時刻（テスト用、通常はnull）
     * @return array<ResourceDto> 配布したログインボーナスの内容
     * @throws \Exception
     */
    public function checkAndGrantLoginBonus(
        int $sysPlayerId,
        ?string $lastLoginAt,
        string $connectionName,
        ?CarbonImmutable $now = null
    ): array {
        $currentTime = $now ?? ClockUtility::now();
        $currentTimeString = $now ? $now->toDateTimeString() : ClockUtility::nowToString();
        
        // DAY_START_TIMEを考慮して、今日初回ログインかをチェック
        // 最終ログイン日時がnullまたは現在時刻と異なるゲーム内日付の場合、ログインボーナスを配布
        if ($lastLoginAt === null || !ClockUtility::isSameGameDay($currentTimeString, $lastLoginAt)) {
            $gameDayStart = ClockUtility::getGameDayStart($currentTimeString);
            return $this->grantLoginBonus($sysPlayerId, $gameDayStart, $connectionName);
        }

        return [];
    }

    /**
     * ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param CarbonImmutable $gameDayStart ゲーム内日付の開始時刻
     * @param string $connectionName シャーディングされたDB接続名
     * @return array<ResourceDto> 配布したログインボーナスの内容
     * @throws \Exception
     */
    private function grantLoginBonus(int $sysPlayerId, CarbonImmutable $gameDayStart, string $connectionName): array
    {
        // 連続ログイン日数を計算（今日を含む）
        $consecutiveDays = $this->getConsecutiveLoginDays($sysPlayerId, $gameDayStart, $connectionName);

        // ログインボーナスマスターから報酬を取得
        $loginBonusData = $this->getLoginBonusByConsecutiveDays($consecutiveDays);

        if ($loginBonusData === null) {
            // ログインボーナスが設定されていない場合は何もしない
            return [];
        }

        // 報酬内容（contents）を取得
        $contents = $this->getLoginBonusContents($loginBonusData['id']);

        if ($contents->isEmpty()) {
            // 報酬が設定されていない場合は何もしない
            return [];
        }

        // Resourceに変換
        $resources = $contents->map(function ($content) {
            return $this->convertToResource($content);
        })->all();

        // ResourceDeliveryServiceで配布
        $this->resourceDeliveryService->addResources($resources);
        $this->resourceDeliveryService->deliver($sysPlayerId);

        // 履歴を記録（複数報酬対応）
        $this->recordLoginBonusHistory($sysPlayerId, $loginBonusData, $contents, $gameDayStart, $connectionName);

        return $resources;
    }

    /**
     * 連続ログイン日数を取得
     *
     * @param int $sysPlayerId
     * @param CarbonImmutable $gameDayStart ゲーム内日付の開始時刻
     * @param string $connectionName シャーディングされたDB接続名
     * @return int
     */
    private function getConsecutiveLoginDays(int $sysPlayerId, CarbonImmutable $gameDayStart, string $connectionName): int
    {
        // 最新のログインボーナス履歴を取得
        $latestHistory = $this->historyRepository->findLatestByPlayer($sysPlayerId, $connectionName);

        if ($latestHistory === null) {
            // 初回ログイン
            return 1;
        }

        $lastReceivedDate = $latestHistory['received_date'];
        $yesterdayStart = $gameDayStart->subDay()->format('Y-m-d H:i:s');

        // 前回の受取日時が昨日（ゲーム内日付）かどうかをチェック
        if (ClockUtility::isSameGameDay($lastReceivedDate, $yesterdayStart)) {
            // 連続ログイン
            // 過去7日間のユニークな受取日数を取得してカウント
            $sevenDaysAgo = $gameDayStart->subDays(7)->format('Y-m-d H:i:s');
            $count = $this->historyRepository->countUniqueDaysSince($sysPlayerId, $sevenDaysAgo, $connectionName);
            
            return $count + 1;
        } else {
            // 連続ログインが途切れた
            return 1;
        }
    }

    /**
     * 連続ログイン日数から該当するログインボーナスを取得
     *
     * @param int $consecutiveDays
     * @return array|null
     */
    private function getLoginBonusByConsecutiveDays(int $consecutiveDays): ?array
    {
        // アクティブなログインボーナス設定のloop_daysを取得
        $loopDays = $this->bonusRepository->getLoopDaysForActiveBonus();

        if ($loopDays === null) {
            return null;
        }

        // loop_daysに基づいてサイクル内の日数を計算
        $dayInCycle = (($consecutiveDays - 1) % $loopDays) + 1;

        // 該当する日のログインボーナスを取得
        return $this->bonusRepository->findActiveByDay($dayInCycle);
    }

    /**
     * ログインボーナスの報酬内容を取得
     *
     * @param string $loginBonusId
     * @return Collection
     */
    private function getLoginBonusContents(string $loginBonusId): Collection
    {
        $contents = $this->bonusRepository->findContentsByLoginBonusId($loginBonusId);
        
        // stdClassに変換して返す（既存コードとの互換性維持）
        return collect($contents)->map(fn($content) => (object) $content);
    }

    /**
     * LoginBonusContentをResourceに変換
     *
     * @param object $content
     * @return ResourceDto
     */
    private function convertToResource(object $content): ResourceDto
    {
        $metadata = [];
        if ($content->is_paid) {
            $metadata['is_paid'] = true;
        }

        return ResourceDto::fromTypeString(
            typeString: $content->content_type,
            id: $content->content_id,
            amount: $content->amount,
            metadata: empty($metadata) ? null : $metadata,
        );
    }

    /**
     * ログインボーナス履歴を記録（複数報酬対応）
     *
     * @param int $sysPlayerId
     * @param array $loginBonusData
     * @param Collection $contents
     * @param CarbonImmutable $gameDayStart ゲーム内日付の開始時刻
     * @param string $connectionName シャーディングされたDB接続名
     * @return void
     */
    private function recordLoginBonusHistory(
        int $sysPlayerId,
        array $loginBonusData,
        Collection $contents,
        CarbonImmutable $gameDayStart,
        string $connectionName
    ): void {
        // 各報酬ごとに履歴を記録
        // received_dateにはゲーム内日付の開始時刻を記録（Y-m-d H:i:s形式）
        $receivedDate = $gameDayStart->format('Y-m-d H:i:s');
        
        foreach ($contents as $content) {
            $this->historyRepository->create([
                'sys_player_id' => $sysPlayerId,
                'mst_login_bonus_id' => $loginBonusData['id'],
                'received_date' => $receivedDate,
                'reward_type' => $content->content_type,
                'reward_id' => $content->content_id,
                'reward_amount' => $content->amount,
                'is_paid' => $content->is_paid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $connectionName);
        }
    }
}
