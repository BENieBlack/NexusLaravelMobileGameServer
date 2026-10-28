<?php

namespace NexusMission\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusMission\Contracts\MissionMasterRepositoryInterface;
use NexusMission\DataTransferObjects\Mission;
use NexusMission\DataTransferObjects\MissionContent;
use NexusMission\DataTransferObjects\MissionReceipt;
use NexusMission\DataTransferObjects\Progress;
use NexusMission\Exceptions\MissionException;
use NexusMission\Repositories\MissionReceiptRepositoryInterface;
use NexusMission\Repositories\ProgressRepositoryInterface;
use NexusMission\ValueObjects\MissionStatus;
use NexusMission\ValueObjects\MissionTrigger;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Services\ResourceDeliveryService;

/**
 * MissionService
 *
 * ミッションのビジネスロジック
 *
 * 進捗は「出来事 × 対象（タグ）」の通算カウンタ（trx_progress）で持ち、ミッションはそれを参照する。
 * 受け取ったかどうかだけをミッションごとに記録する（trx_mission）。
 *
 * 責務:
 * - 出来事（MissionTrigger）を受けて、カウンタを進める
 * - 期間内のミッションの状態一覧を返す
 * - 達成したミッションの報酬を配る
 */
class MissionService
{
    public function __construct(
        private readonly MissionMasterRepositoryInterface $masterRepository,
        private readonly ProgressRepositoryInterface $progressRepository,
        private readonly MissionReceiptRepositoryInterface $receiptRepository,
        private readonly ResourceDeliveryService $deliveryService,
    ) {}

    /**
     * 出来事を受けて、対象が持つタグごとのカウンタと通算のカウンタを進める
     *
     * どのミッションがあるかに関係なく数える。後から足したミッションも過去の実績で達成でき、
     * 同じタグを数えるミッションが複数あっても1つのカウンタを共有する。
     *
     * @return array<int, Progress> 進めたカウンタ
     */
    public function record(int $sysPlayerId, MissionTrigger $missionTrigger): array
    {
        $progresses = $this->progressRepository->selectByPlayerId($sysPlayerId);
        $updated = [];

        foreach ($missionTrigger->findSubTargetIds() as $subTargetId) {
            $current = $this->findProgress($progresses, $missionTrigger->getTriggerType(), $subTargetId);

            $progress = new Progress(
                sysPlayerId: $sysPlayerId,
                triggerType: $missionTrigger->getTriggerType(),
                subTargetId: $subTargetId,
                progressValue: ($current?->getProgressValue() ?? 0) + $missionTrigger->getAmount(),
            );
            $this->progressRepository->save($progress);
            $updated[] = $progress;
        }

        return $updated;
    }

    /**
     * 期間内のミッションの状態を一覧で返す（sort_order昇順）
     *
     * @return array<int, MissionStatus>
     */
    public function findStatuses(int $sysPlayerId): array
    {
        $progresses = $this->progressRepository->selectByPlayerId($sysPlayerId);

        $receiptByMissionId = [];
        foreach ($this->receiptRepository->selectByPlayerId($sysPlayerId) as $missionReceipt) {
            $receiptByMissionId[$missionReceipt->getMstMissionId()] = $missionReceipt;
        }

        $openMissions = array_values(array_filter(
            $this->masterRepository->selectAllMissions(),
            fn (Mission $mission) => $this->isOpen($mission),
        ));
        usort(
            $openMissions,
            fn (Mission $a, Mission $b) => [$a->getSortOrder(), $a->getId()] <=> [$b->getSortOrder(), $b->getId()],
        );

        return array_map(
            fn (Mission $mission) => new MissionStatus(
                mission: $mission,
                progressValue: $this->findProgressValue($progresses, $mission),
                missionReceipt: $receiptByMissionId[$mission->getId()] ?? null,
                missionContents: $this->masterRepository->selectContentsByMissionId($mission->getId()),
            ),
            $openMissions,
        );
    }

    /**
     * 達成したミッションの報酬を受け取る
     *
     * @return MissionStatus 受け取り後の状態（配った報酬を含む）
     *
     * @throws MissionException 存在しない・期間外・未達成・受け取り済みの場合
     */
    public function receive(int $sysPlayerId, string $mstMissionId): MissionStatus
    {
        $mission = $this->masterRepository->selectMissionById($mstMissionId);
        if ($mission === null) {
            throw MissionException::missionNotFound($mstMissionId);
        }

        if (! $this->isOpen($mission)) {
            throw MissionException::missionNotOpen($mstMissionId);
        }

        if ($this->receiptRepository->selectByMissionId($sysPlayerId, $mstMissionId) !== null) {
            throw MissionException::alreadyReceived($mstMissionId);
        }

        $progressValue = $this->findProgressValue($this->progressRepository->selectByPlayerId($sysPlayerId), $mission);
        if ($progressValue < $mission->getTargetValue()) {
            throw MissionException::notCompleted($mstMissionId, $mission->getTargetValue(), $progressValue);
        }

        $missionContents = $this->masterRepository->selectContentsByMissionId($mstMissionId);
        $this->deliver($sysPlayerId, $missionContents);

        $missionReceipt = new MissionReceipt(
            sysPlayerId: $sysPlayerId,
            mstMissionId: $mstMissionId,
            receivedAt: ClockUtility::nowToString(),
        );
        $this->receiptRepository->insert($missionReceipt);

        return new MissionStatus($mission, $progressValue, $missionReceipt, $missionContents);
    }

    /**
     * 報酬を配る
     *
     * @param  array<int, MissionContent>  $missionContents
     */
    private function deliver(int $sysPlayerId, array $missionContents): void
    {
        if ($missionContents === []) {
            return;
        }

        // ResourceDeliveryService は「積んでから配る」形
        $this->deliveryService->addContents(array_map(
            fn (MissionContent $missionContent) => ResourceDeliveryContent::fromArray([
                'type' => $missionContent->getContentType(),
                'id' => $missionContent->getContentMstId(),
                'amount' => $missionContent->getContentQuantity(),
            ]),
            $missionContents,
        ));
        $this->deliveryService->deliver($sysPlayerId);
    }

    /**
     * ミッションが数えるカウンタの値（まだ一度も数えていなければ0）
     *
     * @param  array<int, Progress>  $progresses
     */
    private function findProgressValue(array $progresses, Mission $mission): int
    {
        return $this->findProgress($progresses, $mission->getTriggerType(), $mission->getSubTargetId())
            ?->getProgressValue() ?? 0;
    }

    /**
     * @param  array<int, Progress>  $progresses
     */
    private function findProgress(array $progresses, string $triggerType, string $subTargetId): ?Progress
    {
        foreach ($progresses as $progress) {
            if ($progress->isFor($triggerType, $subTargetId)) {
                return $progress;
            }
        }

        return null;
    }

    /**
     * ミッションが期間内か
     */
    private function isOpen(Mission $mission): bool
    {
        return ClockUtility::isWithin($mission->getStartAt(), $mission->getEndAt());
    }
}
