<?php

namespace App\Domain\Login\Services;

use App\Repositories\Log\LogVipLoginBonusReceiveRepository;
use App\Repositories\Mst\VipLoginBonusRepositoryInterface;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use Nexus\Core\ResourceDelivery\Services\ResourceDeliveryService;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Services\_BaseLoginBonusService;
use NexusVip\Services\VipLevelService;

/**
 * VipLoginBonusService (Domain層)
 *
 * VIPログインボーナスの配布処理を担当するサービス
 * _BaseLoginBonusServiceを継承（通常ログインボーナスと同じ動作）
 *
 * 特性:
 * - 毎日日跨ぎ後にもらえる
 * - VIPレベルに応じて報酬が異なる
 * - 設定日数でループする（継承元のデフォルト）
 * - 1日ログインしなくてもスキップしない（継承元のデフォルト）
 */
class VipLoginBonusService extends _BaseLoginBonusService
{
    public function __construct(
        ResourceDeliveryService $resourceDeliveryService,
        private readonly VipLoginBonusRepositoryInterface $vipBonusRepository,
        private readonly VipLoginBonusHistoryRepositoryInterface $vipHistoryRepository,
        private readonly LogVipLoginBonusReceiveRepository $logVipLoginBonusReceiveRepository,
        private readonly SysPlayerRepository $playerRepository,
        private readonly VipLevelService $vipLevelService,
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
        if ($lastLoginAt !== null && ClockUtility::isSameGameDay($currentTimeString, $lastLoginAt)) {
            return false;
        }

        // VIPレベルを取得
        $vipLevel = $this->resolveVipLevel($sysPlayerId);

        if ($vipLevel === null) {
            return false;
        }

        // VIPレベルに対応するVIPログインボーナスがあるかチェック
        $vipBonus = $this->vipBonusRepository->selectActiveByVipLevel($vipLevel);

        return $vipBonus !== null;
    }

    /**
     * {@inheritDoc}
     */
    protected function findLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array
    {
        // プレイヤーのVIPレベルを取得
        $vipLevel = $this->resolveVipLevel($sysPlayerId);

        if ($vipLevel === null) {
            return null;
        }

        // VIPレベルに対応するVIPログインボーナス設定を取得
        $vipBonus = $this->vipBonusRepository->selectActiveByVipLevel($vipLevel);

        if ($vipBonus === null) {
            return null;
        }

        // VIPレベルを追加
        $vipBonus['current_vip_level'] = $vipLevel;

        return $vipBonus;
    }

    /**
     * {@inheritDoc}
     */
    protected function findBonusContents(array $bonusData, int $currentDay): CustomCollection
    {
        // 指定日数の報酬内容を取得
        $contents = $this->vipBonusRepository->selectContentsByBonusIdAndDay(
            $bonusData['id'],
            $currentDay
        );

        // stdClassに変換して返す
        return $contents->map(fn ($content) => (object) $content);
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
        $receivedAt = $this->calcGameDayStart()->format('Y-m-d H:i:s');

        // 最新状態はtrx、受取履歴はlogへ記録
        $this->vipHistoryRepository->upsertLatest(
            sysPlayerId: $sysPlayerId,
            mstVipLoginBonusId: $bonusData['id'],
            day: $currentDay,
            vipLevel: $bonusData['current_vip_level'],
            receivedAt: $receivedAt,
        );

        foreach ($contents as $content) {
            $this->logVipLoginBonusReceiveRepository->insertReceive(
                sysPlayerId: $sysPlayerId,
                mstVipLoginBonusId: $bonusData['id'],
                day: $currentDay,
                vipLevel: $bonusData['current_vip_level'],
                receivedAt: $receivedAt,
                rewardType: $content->content_type,
                rewardMstId: $content->content_mst_id,
                rewardAmount: $content->content_quantity * $content->amount,
                isPaid: $content->is_paid ?? false,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function findLastReceivedDay(int $sysPlayerId): ?int
    {
        return $this->vipHistoryRepository->selectLatestByPlayerId($sysPlayerId)?->getDay();
    }

    /**
     * {@inheritDoc}
     */
    protected function findLoopDays(int $sysPlayerId): ?int
    {
        $vipLevel = $this->resolveVipLevel($sysPlayerId);

        if ($vipLevel === null) {
            return null;
        }

        $vipBonus = $this->vipBonusRepository->selectActiveByVipLevel($vipLevel);

        return $vipBonus['loop_days'] ?? null;
    }

    /**
     * プレイヤーのVIPレベルを取得
     *
     * VIPレベルはカラムに保持せず、累積VIPポイントから算出する。
     *
     * @return int|null VIPレベル、プレイヤーが存在しない場合はnull
     */
    private function resolveVipLevel(int $sysPlayerId): ?int
    {
        // RepositoryはModelを返す。VIPポイントはModelから直接読む
        $player = $this->playerRepository->selectById($sysPlayerId);

        if ($player === null) {
            return null;
        }

        return $this->vipLevelService->calculateLevel($player->getVipPoint());
    }
}
