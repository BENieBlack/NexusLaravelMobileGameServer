<?php

namespace NexusRewardTrack\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;
use NexusRewardTrack\DataTransferObjects\RewardTrack;
use NexusRewardTrack\DataTransferObjects\RewardTrackLine;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;
use NexusRewardTrack\Exceptions\RewardTrackException;
use NexusRewardTrack\Repositories\RewardTrackLineRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackMilestoneRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackRepositoryInterface;

/**
 * RewardTrackService
 *
 * RewardTrack（バトルパス型報酬トラック）のビジネスロジック。
 *
 * 責務:
 * - 進捗加算
 * - パスライン購入処理
 * - 解放済みマイルストーン一覧の提供
 * - 報酬受け取り処理
 * - トラックサマリーの提供
 */
class RewardTrackService
{
    public function __construct(
        private readonly RewardTrackMasterRepositoryInterface $masterRepository,
        private readonly RewardTrackRepositoryInterface $progressRepository,
        private readonly RewardTrackLineRepositoryInterface $lineRepository,
        private readonly RewardTrackMilestoneRepositoryInterface $milestoneRepository,
        private readonly ResourceDeliveryService $deliveryService,
    ) {}

    // ==========================================
    // 進捗管理
    // ==========================================

    /**
     * 進捗を加算する
     *
     * @throws RewardTrackException トラックが存在しない・期間外の場合
     */
    public function addProgress(int $sysPlayerId, string $trackId, int $delta, string $connectionName): RewardTrack
    {
        $track = $this->masterRepository->selectTrackById($trackId);
        if ($track === null) {
            throw RewardTrackException::trackNotFound($trackId);
        }

        $this->assertTrackActive($track);

        return $this->progressRepository->addProgress($sysPlayerId, $trackId, $delta, $connectionName);
    }

    /**
     * 進捗を直接設定する（player_level タイプ等、外部から値をセットする場合）
     *
     * @throws RewardTrackException トラックが存在しない・期間外の場合
     */
    public function setProgress(int $sysPlayerId, string $trackId, int $progress, string $connectionName): RewardTrack
    {
        $track = $this->masterRepository->selectTrackById($trackId);
        if ($track === null) {
            throw RewardTrackException::trackNotFound($trackId);
        }

        $this->assertTrackActive($track);

        return $this->progressRepository->upsertProgress($sysPlayerId, $trackId, $progress, $connectionName);
    }

    // ==========================================
    // ライン購入
    // ==========================================

    /**
     * 有料ラインをプレイヤーに付与する（課金完了後に呼ぶ）
     *
     * @throws RewardTrackException 既に所持している場合・無料ラインは購入不可
     */
    public function grantLine(int $sysPlayerId, string $lineId, int $mstInAppPurchaseId, string $connectionName): RewardTrackLine
    {
        // ライン定義を取得
        $lines = $this->masterRepository->selectLinesByTrackId($this->resolveTrackIdFromLineId($lineId));
        $line = collect($lines)->firstWhere('id', $lineId);

        if ($line === null) {
            throw RewardTrackException::lineNotFound($lineId);
        }

        if ($line['is_free']) {
            throw RewardTrackException::freeLineNotPurchasable($lineId);
        }

        if ($this->lineRepository->hasLine($sysPlayerId, $lineId, $connectionName)) {
            throw RewardTrackException::lineAlreadyOwned($lineId);
        }

        return $this->lineRepository->insertLine(
            $sysPlayerId,
            $lineId,
            $mstInAppPurchaseId,
            ClockUtility::nowToString(),
            $connectionName
        );
    }

    // ==========================================
    // トラックサマリー
    // ==========================================

    /**
     * プレイヤーのトラックサマリーを取得する
     *
     * 返却内容:
     * - マスター情報（ライン・マイルストーン・報酬）
     * - 進捗値
     * - 所持ライン
     * - 受け取り済みマイルストーン
     * - 各マイルストーンの受け取り可否
     *
     * @return array{
     *   track: array<string, mixed>,
     *   lines: array<int, array<string, mixed>>,
     *   milestones: array<int, array<string, mixed>>,
     *   current_progress: int,
     *   owned_line_ids: array<int, string>,
     *   received_key_set: array<string, bool>
     * }
     */
    public function getSummary(int $sysPlayerId, string $trackId, string $connectionName): array
    {
        $track = $this->masterRepository->selectTrackById($trackId);
        if ($track === null) {
            throw RewardTrackException::trackNotFound($trackId);
        }

        $lines = $this->masterRepository->selectLinesByTrackId($trackId);
        $milestones = $this->masterRepository->selectMilestonesByTrackId($trackId);

        $milestoneIds = array_column($milestones, 'id');
        $contents = $this->masterRepository->selectContentsByMilestoneIds($milestoneIds);

        // コンテンツをマイルストーン×ラインでグループ化
        $contentMap = [];
        foreach ($contents as $content) {
            $contentMap[$content['mst_reward_track_milestone_id']][$content['mst_reward_track_line_id']][] = $content;
        }

        // マイルストーンにコンテンツを付与
        $milestonesWithContents = array_map(function ($milestone) use ($contentMap) {
            $milestone['contents'] = $contentMap[$milestone['id']] ?? [];

            return $milestone;
        }, $milestones);

        // プレイヤーデータ
        $progress = $this->progressRepository->findByPlayerAndTrack($sysPlayerId, $trackId, $connectionName);
        $lineIds = array_column($lines, 'id');

        $ownedLineIds = $this->lineRepository->findOwnedLineIds($sysPlayerId, $lineIds, $connectionName);
        $receivedKeySet = $this->milestoneRepository->findReceivedKeySet($sysPlayerId, $trackId, $connectionName);

        // 無料ラインは常に所持
        $freeLineId = $this->masterRepository->selectFreeLineId($trackId);
        if ($freeLineId !== null && ! in_array($freeLineId, $ownedLineIds, true)) {
            $ownedLineIds[] = $freeLineId;
        }

        return [
            'track' => $track,
            'lines' => $lines,
            'milestones' => $milestonesWithContents,
            'current_progress' => $progress?->getCurrentProgress() ?? 0,
            'owned_line_ids' => $ownedLineIds,
            'received_key_set' => $receivedKeySet,
        ];
    }

    // ==========================================
    // 報酬受け取り
    // ==========================================

    /**
     * マイルストーン×ラインの報酬を受け取る
     *
     * @throws RewardTrackException 受け取り条件を満たさない場合
     */
    public function receiveMilestone(
        int $sysPlayerId,
        string $milestoneId,
        string $lineId,
        string $connectionName
    ): RewardTrackMilestone {
        // マイルストーンのトラックIDを解決
        $trackId = $this->resolveTrackIdFromMilestoneId($milestoneId);

        $track = $this->masterRepository->selectTrackById($trackId);
        if ($track === null) {
            throw RewardTrackException::trackNotFound($trackId);
        }

        // トラックが期間内か確認
        $this->assertTrackActive($track);

        // プレイヤーの進捗を取得
        $progress = $this->progressRepository->findByPlayerAndTrack($sysPlayerId, $trackId, $connectionName);
        $currentProgress = $progress?->getCurrentProgress() ?? 0;

        // マイルストーンを取得
        $milestones = $this->masterRepository->selectMilestonesByTrackId($trackId);
        $milestone = collect($milestones)->firstWhere('id', $milestoneId);

        if ($milestone === null) {
            throw RewardTrackException::milestoneNotFound($milestoneId);
        }

        // 進捗チェック（解放済みか）
        if ($currentProgress < $milestone['required_progress']) {
            throw RewardTrackException::progressNotEnough(
                (int) $milestone['required_progress'],
                $currentProgress,
            );
        }

        // ラインの所持チェック
        $lines = $this->masterRepository->selectLinesByTrackId($trackId);
        $lineData = collect($lines)->firstWhere('id', $lineId);
        if ($lineData === null) {
            throw RewardTrackException::lineNotFound($lineId);
        }

        $freeLineId = $this->masterRepository->selectFreeLineId($trackId);
        $isFree = ($lineId === $freeLineId);

        if (! $isFree && ! $this->lineRepository->hasLine($sysPlayerId, $lineId, $connectionName)) {
            throw RewardTrackException::lineNotOwned($lineId);
        }

        // 二重受け取りチェック
        if ($this->milestoneRepository->hasReceived($sysPlayerId, $milestoneId, $lineId, $connectionName)) {
            throw RewardTrackException::alreadyReceived($milestoneId, $lineId);
        }

        // 報酬コンテンツを取得
        $contents = $this->masterRepository->selectContentsByMilestoneIds([$milestoneId]);
        $lineContents = array_filter($contents, fn ($c) => $c['mst_reward_track_line_id'] === $lineId);

        if (! empty($lineContents)) {
            // 報酬を配布
            $deliveryContents = array_map(
                fn ($c) => ResourceDeliveryContent::fromArray([
                    // Resource::fromArray が読むキーは type / id / amount。
                    // マスター側の列名（content_mst_id, content_quantity）のままでは
                    // id と amount が埋まらず、配布時に TypeError になる
                    'type' => $c['content_type'],
                    'id' => $c['content_mst_id'],
                    'amount' => $c['content_quantity'] * $c['amount'],
                    'metadata' => empty($c['content_option']) ? null : $c['content_option'],
                ]),
                array_values($lineContents)
            );

            // ResourceDeliveryService は「積んでから配る」形。
            // deliverMultiple というメソッドは存在しない
            $this->deliveryService->addContents($deliveryContents);
            $this->deliveryService->deliver($sysPlayerId);
        }

        // 受け取り履歴を記録
        return $this->milestoneRepository->insertReceipt(
            $sysPlayerId,
            $milestoneId,
            $lineId,
            ClockUtility::nowToString(),
            $connectionName
        );
    }

    // ==========================================
    // プライベートヘルパー
    // ==========================================

    /**
     * トラックが現在アクティブか確認する
     *
     * @param  array<string, mixed>  $track
     */
    private function assertTrackActive(array $track): void
    {
        $now = ClockUtility::nowToString();

        if (! ClockUtility::greaterThanOrEqual($track['start_at'])) {
            throw RewardTrackException::trackNotStarted((string) $track['id']);
        }

        if ($track['end_at'] !== null && ! ClockUtility::lessThanOrEqual($track['end_at'])) {
            throw RewardTrackException::trackEnded((string) $track['id']);
        }
    }

    /**
     * ラインIDからトラックIDを解決する（マスターデータ経由）
     */
    private function resolveTrackIdFromLineId(string $lineId): string
    {
        $activeTracks = $this->masterRepository->selectActiveTracks();
        foreach ($activeTracks as $track) {
            $lines = $this->masterRepository->selectLinesByTrackId($track['id']);
            foreach ($lines as $line) {
                if ($line['id'] === $lineId) {
                    return $track['id'];
                }
            }
        }
        throw RewardTrackException::lineNotFound($lineId);
    }

    /**
     * マイルストーンIDからトラックIDを解決する（マスターデータ経由）
     */
    private function resolveTrackIdFromMilestoneId(string $milestoneId): string
    {
        $activeTracks = $this->masterRepository->selectActiveTracks();
        foreach ($activeTracks as $track) {
            $milestones = $this->masterRepository->selectMilestonesByTrackId($track['id']);
            foreach ($milestones as $milestone) {
                if ($milestone['id'] === $milestoneId) {
                    return $track['id'];
                }
            }
        }
        throw RewardTrackException::milestoneNotFound($milestoneId);
    }
}
