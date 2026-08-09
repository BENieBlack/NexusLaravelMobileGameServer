<?php

namespace App\Domain\Login\Services;

use App\Repositories\Mst\VipLoginBonusRepositoryInterface;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use NexusLogin\Services\_BaseLoginBonusService;
use Nexus\Core\Support\CustomCollection;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use Nexus\Core\Utilities\ClockUtility;

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
        private readonly SysPlayerRepository $playerRepository,
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
        $player = $this->playerRepository->findVipInfoById($sysPlayerId);

        if ($player === null) {
            return false;
        }

        // VIPレベルに対応するVIPログインボーナスがあるかチェック
        $vipBonus = $this->vipBonusRepository->findActiveByVipLevel($player->vip_level);

        return $vipBonus !== null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array
    {
        // プレイヤーのVIPレベルを取得
        $player = $this->playerRepository->findVipInfoById($sysPlayerId);

        if ($player === null) {
            return null;
        }

        // VIPレベルに対応するVIPログインボーナス設定を取得
        $vipBonus = $this->vipBonusRepository->findActiveByVipLevel($player->vip_level);

        if ($vipBonus === null) {
            return null;
        }

        // VIPレベルを追加
        $vipBonus['current_vip_level'] = $player->vip_level;

        return $vipBonus;
    }

    /**
     * {@inheritDoc}
     */
    protected function getBonusContents(array $bonusData, int $currentDay): CustomCollection
    {
        // 指定日数の報酬内容を取得
        $contents = $this->vipBonusRepository->findContentsByBonusIdAndDay(
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
        CustomCollection $contents,
        string $connectionName
    ): void {
        $receivedAt = $this->getGameDayStart()->format('Y-m-d H:i:s');

        // VIPログインボーナス履歴テーブルに記録
        $this->vipHistoryRepository->create([
            'sys_player_id' => $sysPlayerId,
            'mst_vip_login_bonus_id' => $bonusData['id'],
            'day' => $currentDay,
            'vip_level' => $bonusData['current_vip_level'],
            'received_at' => $receivedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ], $connectionName);
    }

    /**
     * {@inheritDoc}
     */
    protected function getLastReceivedDay(int $sysPlayerId, string $connectionName): ?int
    {
        $lastHistory = $this->vipHistoryRepository->findLatestByPlayerId($sysPlayerId, $connectionName);

        return $lastHistory['day'] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getLoopDays(int $sysPlayerId): ?int
    {
        $player = $this->playerRepository->findVipInfoById($sysPlayerId);

        if ($player === null) {
            return null;
        }

        $vipBonus = $this->vipBonusRepository->findActiveByVipLevel($player->vip_level);

        return $vipBonus['loop_days'] ?? null;
    }
}
