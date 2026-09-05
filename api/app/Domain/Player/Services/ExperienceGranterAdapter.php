<?php

namespace App\Domain\Player\Services;

use App\Domain\Equipment\Services\EquipmentLevelService;
use App\Domain\Unit\Services\UnitLevelService;
use App\Repositories\Trx\TrxEquipmentRepository;
use App\Repositories\Trx\TrxUnitRepository;
use NexusLevel\Services\PlayerLevelService;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;

/**
 * ExperienceGranterAdapter
 *
 * nexus-resource-deliveryのExperienceGranterInterfaceを実装し、
 * 付与先の種別に応じてApplication層のレベルサービスへ振り分ける。
 *
 * ユニットと装備は対象がプレイヤーの所有物であることを確認してから加算する。
 * なお対象の取得はセッションのプレイヤーを見るRepositoryを通るため、
 * 既存のユニット・装備への加算はセッション本人に対してのみ行える
 * （運営からの一斉配布で他人のユニットに経験値を入れることは想定しない）。
 */
class ExperienceGranterAdapter implements ExperienceGranterInterface
{
    public function __construct(
        private readonly PlayerLevelService $playerLevelService,
        private readonly UnitLevelService $unitLevelService,
        private readonly EquipmentLevelService $equipmentLevelService,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function grantExperience(
        int $sysPlayerId,
        int $amount,
        string $targetType = self::TARGET_PLAYER,
        ?string $targetId = null
    ): void {
        match ($targetType) {
            self::TARGET_PLAYER => $this->playerLevelService->addExp($sysPlayerId, $amount),
            self::TARGET_UNIT => $this->grantUnitExperience(
                $sysPlayerId,
                $this->requireTargetId($targetId, $targetType),
                $amount
            ),
            self::TARGET_EQUIPMENT => $this->grantEquipmentExperience(
                $sysPlayerId,
                $this->requireTargetId($targetId, $targetType),
                $amount
            ),
            default => throw new \InvalidArgumentException("Unsupported experience target: {$targetType}"),
        };
    }

    /**
     * ユニットに経験値を加算する
     *
     * @throws \InvalidArgumentException 対象がプレイヤーの所有ユニットでない場合
     */
    private function grantUnitExperience(int $sysPlayerId, int $trxUnitId, int $amount): void
    {
        $trxUnit = $this->trxUnitRepository->selectById($trxUnitId);

        if ($trxUnit === null || $trxUnit->getSysPlayerId() !== $sysPlayerId) {
            throw new \InvalidArgumentException("Unit does not belong to player: {$trxUnitId}");
        }

        $this->unitLevelService->addExpWithDetails($trxUnitId, $amount);
    }

    /**
     * 装備に経験値を加算する
     *
     * @throws \InvalidArgumentException 対象がプレイヤーの所有装備でない場合
     */
    private function grantEquipmentExperience(int $sysPlayerId, int $trxEquipmentId, int $amount): void
    {
        $trxEquipment = $this->trxEquipmentRepository->selectById($trxEquipmentId);

        if ($trxEquipment === null || $trxEquipment->getSysPlayerId() !== $sysPlayerId) {
            throw new \InvalidArgumentException("Equipment does not belong to player: {$trxEquipmentId}");
        }

        $this->equipmentLevelService->addExpAndReturn($trxEquipmentId, $amount);
    }

    /**
     * 対象IDを取り出す（未指定・数値でない場合は例外）
     *
     * @throws \InvalidArgumentException
     */
    private function requireTargetId(?string $targetId, string $targetType): int
    {
        if ($targetId === null || ! ctype_digit($targetId)) {
            throw new \InvalidArgumentException("Target id is required for {$targetType} experience");
        }

        return (int) $targetId;
    }
}
