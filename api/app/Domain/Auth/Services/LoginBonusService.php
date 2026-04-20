<?php

namespace App\Domain\Auth\Services;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\Services\DeliveryService;
use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use App\Models\Trx\TrxLoginBonusHistory;
use App\Persistence\ApiSession;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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
        private readonly DeliveryService $deliveryService,
    ) {
    }

    /**
     * 今日初回ログインかどうかをチェックし、ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param \DateTime|null $lastLoginAt 最終ログイン日時（UTC）
     * @param CarbonImmutable $currentTime 現在日時（UTC）
     * @return array<DeliveryContent> 配布したログインボーナスの内容
     * @throws \Exception
     */
    public function checkAndGrantLoginBonus(
        int $sysPlayerId,
        ?\DateTime $lastLoginAt,
        CarbonImmutable $currentTime
    ): array {
        // UTC0時を境界として、今日初回ログインかをチェック
        $today = $currentTime->startOfDay();
        
        // 最終ログイン日時がnullまたは今日より前の場合、ログインボーナスを配布
        if ($lastLoginAt === null || Carbon::instance($lastLoginAt)->startOfDay()->lt($today)) {
            return $this->grantLoginBonus($sysPlayerId, $today);
        }

        return [];
    }

    /**
     * ログインボーナスを配布する
     *
     * @param int $sysPlayerId
     * @param CarbonImmutable $today 今日の日付（UTC、00:00:00）
     * @return array<DeliveryContent> 配布したログインボーナスの内容
     * @throws \Exception
     */
    private function grantLoginBonus(int $sysPlayerId, CarbonImmutable $today): array
    {
        // 連続ログイン日数を計算（今日を含む）
        $consecutiveDays = $this->getConsecutiveLoginDays($sysPlayerId, $today);

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

        // DeliveryContentに変換
        $deliveryContents = $contents->map(function ($content) {
            return $this->convertToDeliveryContent($content);
        })->all();

        // DeliveryServiceで配布
        $this->deliveryService->addContents($deliveryContents);
        $this->deliveryService->deliver($sysPlayerId);

        // 履歴を記録（複数報酬対応）
        $this->recordLoginBonusHistory($sysPlayerId, $loginBonus, $contents, $today);

        return $deliveryContents;
    }

    /**
     * 連続ログイン日数を取得
     *
     * @param int $sysPlayerId
     * @param CarbonImmutable $today
     * @return int
     */
    private function getConsecutiveLoginDays(int $sysPlayerId, CarbonImmutable $today): int
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

        $lastReceivedDate = Carbon::parse($latestHistory->received_date);
        $yesterday = $today->copy()->subDay();

        if ($lastReceivedDate->equalTo($yesterday)) {
            // 連続ログイン
            // 過去7日間のユニークな受取日数を取得してカウント
            $count = DB::connection($connection)
                ->table('trx_login_bonus_history')
                ->where('sys_player_id', $sysPlayerId)
                ->where('received_date', '>=', $today->copy()->subDays(7)->toDateString())
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
     * MstLoginBonusContentをDeliveryContentに変換
     *
     * @param \App\Models\Mst\MstLoginBonusContent $content
     * @return DeliveryContent
     */
    private function convertToDeliveryContent($content): DeliveryContent
    {
        $metadata = [];
        if ($content->is_paid) {
            $metadata['is_paid'] = true;
        }

        return new DeliveryContent(
            type: $content->content_type,
            id: $content->content_id,
            amount: $content->amount,
            metadata: $metadata,
        );
    }

    /**
     * ログインボーナス履歴を記録（複数報酬対応）
     *
     * @param int $sysPlayerId
     * @param MstLoginBonus $loginBonus
     * @param \Illuminate\Support\Collection $contents
     * @param CarbonImmutable $today
     * @return void
     */
    private function recordLoginBonusHistory(
        int $sysPlayerId,
        MstLoginBonus $loginBonus,
        Collection $contents,
        CarbonImmutable $today
    ): void {
        // プレイヤーのシャーディングされたDB接続を取得
        $connection = $this->apiSession->getConnectionNameValue();

        // 各報酬ごとに履歴を記録
        foreach ($contents as $content) {
            DB::connection($connection)->table('trx_login_bonus_history')->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_login_bonus_id' => $loginBonus->id,
                'received_date' => $today->toDateString(),
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
