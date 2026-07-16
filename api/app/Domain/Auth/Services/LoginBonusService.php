<?php

namespace App\Domain\Auth\Services;

use NexusResource\DTOs\Resource;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use App\Models\Trx\TrxLoginBonusHistory;
use App\Persistence\ApiSession;
use Carbon\CarbonImmutable;
use NexusUtilities\ClockUtility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LoginBonusService
 *
 * ログインボーナスの配布処理を担当するサービス
 */
class LoginBonusService
{
    public function __construct(
        private readonly ApiSession $apiSession,
        private readonly ResourceDeliveryService $resourceDeliveryService,
    ) {
    }

    /**
     * 今日初回ログインかどうかをチェックし、ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param string|null $lastLoginAt 最終ログイン日時（UTC、文字列形式）
     * @return array<Resource> 配布したログインボーナスの内容
     * @throws \Exception
     */
    public function checkAndGrantLoginBonus(
        int $sysPlayerId,
        ?string $lastLoginAt
    ): array {
        $currentTime = ClockUtility::now();
        $currentTimeString = ClockUtility::nowToString();
        
        // DAY_START_TIMEを考慮して、今日初回ログインかをチェック
        // 最終ログイン日時がnullまたは現在時刻と異なるゲーム内日付の場合、ログインボーナスを配布
        if ($lastLoginAt === null || !ClockUtility::isSameGameDay($currentTimeString, $lastLoginAt)) {
            $gameDayStart = ClockUtility::getGameDayStart($currentTimeString);
            return $this->grantLoginBonus($sysPlayerId, $gameDayStart);
        }

        return [];
    }

    /**
     * ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param CarbonImmutable $gameDayStart ゲーム内日付の開始時刻
     * @return array<Resource> 配布したログインボーナスの内容
     * @throws \Exception
     */
    private function grantLoginBonus(int $sysPlayerId, CarbonImmutable $gameDayStart): array
    {
        // 連続ログイン日数を計算（今日を含む）
        $consecutiveDays = $this->getConsecutiveLoginDays($sysPlayerId, $gameDayStart);

        // ログインボーナスマスターから報酬を取得
        $loginBonus = $this->getLoginBonusByConsecutiveDays($consecutiveDays);

        if ($loginBonus === null) {
            // ログインボーナスが設定されていない場合は何もしない
            return [];
        }

        // 報酬内容（contents）を取得
        $contents = $loginBonus->contents()->orderBy('sort_order')->get();

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
        $this->recordLoginBonusHistory($sysPlayerId, $loginBonus, $contents, $gameDayStart);

        return $resources;
    }

    /**
     * 連続ログイン日数を取得
     *
     * @param int $sysPlayerId
     * @param CarbonImmutable $gameDayStart ゲーム内日付の開始時刻
     * @return int
     */
    private function getConsecutiveLoginDays(int $sysPlayerId, CarbonImmutable $gameDayStart): int
    {
        // プレイヤーのシャーディングされたDB接続を取得
        $connection = $this->apiSession->getConnectionNameValue();

        // 最新のログインボーナス履歴を取得
        $latestHistory = DB::connection($connection)
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $sysPlayerId)
            ->orderBy('received_date', 'desc')
            ->first();

        if ($latestHistory === null) {
            // 初回ログイン
            return 1;
        }

        $lastReceivedDate = $latestHistory->received_date;
        $yesterdayStart = $gameDayStart->subDay()->format('Y-m-d H:i:s');

        // 前回の受取日時が昨日（ゲーム内日付）かどうかをチェック
        if (ClockUtility::isSameGameDay($lastReceivedDate, $yesterdayStart)) {
            // 連続ログイン
            // 過去7日間のユニークな受取日数を取得してカウント
            $sevenDaysAgo = $gameDayStart->subDays(7)->format('Y-m-d H:i:s');
            $count = DB::connection($connection)
                ->table('trx_login_bonus_history')
                ->where('sys_player_id', $sysPlayerId)
                ->where('received_date', '>=', $sevenDaysAgo)
                ->distinct()
                ->count('received_date');
            
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
     * @return MstLoginBonus|null
     */
    private function getLoginBonusByConsecutiveDays(int $consecutiveDays): ?MstLoginBonus
    {
        // アクティブなログインボーナス設定を取得
        $loginBonus = MstLoginBonus::where('is_active', true)->first();

        if ($loginBonus === null) {
            return null;
        }

        // loop_daysに基づいてサイクル内の日数を計算
        $dayInCycle = (($consecutiveDays - 1) % $loginBonus->loop_days) + 1;

        // 該当する日のログインボーナスを取得
        return MstLoginBonus::where('day', $dayInCycle)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 指定された日数のログインボーナスを取得
     *
     * @param int $day
     * @return MstLoginBonus|null
     * @deprecated Use getLoginBonusByConsecutiveDays instead
     */
    private function getLoginBonusByDay(int $day): ?MstLoginBonus
    {
        return MstLoginBonus::where('day', $day)
            ->where('is_active', true)
            ->first();
    }

    /**
     * MstLoginBonusContentをResourceに変換
     *
     * @param \App\Models\Mst\MstLoginBonusContent $content
     * @return Resource
     */
    private function convertToResource($content): Resource
    {
        $metadata = [];
        if ($content->is_paid) {
            $metadata['is_paid'] = true;
        }

        return Resource::fromTypeString(
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
     * @param MstLoginBonus $loginBonus
     * @param \Illuminate\Support\Collection $contents
     * @param CarbonImmutable $gameDayStart ゲーム内日付の開始時刻
     * @return void
     */
    private function recordLoginBonusHistory(
        int $sysPlayerId,
        MstLoginBonus $loginBonus,
        Collection $contents,
        CarbonImmutable $gameDayStart
    ): void {
        // プレイヤーのシャーディングされたDB接続を取得
        $connection = $this->apiSession->getConnectionNameValue();

        // 各報酬ごとに履歴を記録
        // received_dateにはゲーム内日付の開始時刻を記録（Y-m-d H:i:s形式）
        $receivedDate = $gameDayStart->format('Y-m-d H:i:s');
        
        foreach ($contents as $content) {
            DB::connection($connection)->table('trx_login_bonus_history')->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_login_bonus_id' => $loginBonus->id,
                'received_date' => $receivedDate,
                'reward_type' => $content->content_type,
                'reward_id' => $content->content_id,
                'reward_amount' => $content->amount,
                'is_paid' => $content->is_paid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
