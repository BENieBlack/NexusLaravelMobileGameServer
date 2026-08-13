<?php

namespace NexusStamina\Services;

use NexusStamina\Constants\StaminaConst;
use NexusStamina\Dto\StaminaDto;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use Nexus\Core\Utilities\ClockUtility;

/**
 * StaminaService
 *
 * プレイヤーのスタミナ管理を担当するサービス
 */
class StaminaService
{
    /**
     * スタミナ回復間隔（秒）
     * デフォルト: 5分 = 300秒で1ポイント回復
     */
    private const RECOVERY_INTERVAL_SECONDS = 300;

    public function __construct(
        private readonly StaminaRepositoryInterface $staminaRepository,
        private readonly PlayerLevelServiceInterface $playerLevelService,
    ) {}

    /**
     * プレイヤーのスタミナ情報を取得（時間経過での自動回復を適用）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $type  スタミナタイプ
     * @return StaminaDto|null スタミナDTO
     */
    public function getStamina(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): ?StaminaDto
    {
        $stamina = $this->staminaRepository->selectByPlayerAndType($sysPlayerId, $type);

        if ($stamina === null) {
            return null;
        }

        // プレイヤーのレベルから最大スタミナを取得
        $maxStamina = $this->playerLevelService->getMaxStamina($sysPlayerId);

        // 自動回復を適用
        $this->applyAutoRecovery($stamina, $maxStamina);

        return $stamina;
    }

    /**
     * 新しいプレイヤーのスタミナを初期化
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $initialStamina  初期スタミナ量
     * @param  string  $type  スタミナタイプ
     * @return StaminaDto 作成されたスタミナDTO
     */
    public function initializeStamina(int $sysPlayerId, int $initialStamina, string $type = StaminaConst::TYPE_NORMAL): StaminaDto
    {
        $now = ClockUtility::nowToString();

        $staminaDto = new StaminaDto(
            sysPlayerId: $sysPlayerId,
            type: $type,
            currentStamina: $initialStamina,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: $now
        );

        return $this->staminaRepository->insert($staminaDto);
    }

    /**
     * スタミナを消費
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $amount  消費量
     * @param  string  $type  スタミナタイプ
     * @return array{success: bool, remaining: int, message: string}
     */
    public function consumeStamina(int $sysPlayerId, int $amount, string $type = StaminaConst::TYPE_NORMAL): array
    {
        $stamina = $this->getStamina($sysPlayerId, $type);

        if ($stamina === null) {
            return [
                'success' => false,
                'remaining' => 0,
                'message' => 'Stamina record not found',
            ];
        }

        // スタミナ不足チェック
        if (! $stamina->hasEnoughStamina($amount)) {
            return [
                'success' => false,
                'remaining' => $stamina->getCurrentStamina(),
                'message' => 'Insufficient stamina',
            ];
        }

        // スタミナを消費
        $stamina->setCurrentStamina($stamina->getCurrentStamina() - $amount);

        $this->staminaRepository->persist($stamina);

        return [
            'success' => true,
            'remaining' => $stamina->getCurrentStamina(),
            'message' => 'Stamina consumed successfully',
        ];
    }

    /**
     * スタミナ回復アイテム使用
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $amount  回復量
     * @param  string  $type  スタミナタイプ
     * @return array{success: bool, total: int, message: string}
     */
    public function recoverStaminaByItem(int $sysPlayerId, int $amount, string $type = StaminaConst::TYPE_NORMAL): array
    {
        $stamina = $this->getStamina($sysPlayerId, $type);

        if ($stamina === null) {
            return [
                'success' => false,
                'total' => 0,
                'message' => 'Stamina record not found',
            ];
        }

        // スタミナを回復（最大値を超過可能）
        $stamina->setCurrentStamina($stamina->getCurrentStamina() + $amount);

        $this->staminaRepository->persist($stamina);

        return [
            'success' => true,
            'total' => $stamina->getCurrentStamina(),
            'message' => 'Stamina recovered successfully',
        ];
    }

    /**
     * 時間経過による自動回復を適用
     *
     * @param  StaminaDto  $staminaDto  スタミナDTO
     * @param  int  $maxStamina  プレイヤーの最大スタミナ
     * @return bool 自動回復が発生したかどうか
     */
    private function applyAutoRecovery(StaminaDto $staminaDto, int $maxStamina): bool
    {
        // すでに最大値の場合は回復不要
        if ($staminaDto->isCurrentStaminaFull($maxStamina)) {
            return false;
        }

        // 経過秒数を計算
        $elapsedSeconds = ClockUtility::diffInSeconds($staminaDto->getLastRecoveryAt());

        // 回復速度倍率を適用
        $effectiveElapsedSeconds = $elapsedSeconds * $staminaDto->getRecoveryRateMultiplier();

        // 回復ポイント数を計算
        $recoveredPoints = (int) floor($effectiveElapsedSeconds / self::RECOVERY_INTERVAL_SECONDS);

        if ($recoveredPoints > 0) {
            // 新しいスタミナ値を計算
            $newStamina = min($staminaDto->getCurrentStamina() + $recoveredPoints, $maxStamina);

            // 次回回復基準時刻を計算
            $recoveredSeconds = $recoveredPoints * self::RECOVERY_INTERVAL_SECONDS;
            $lastRecoveryAt = ClockUtility::parse($staminaDto->getLastRecoveryAt());
            $newLastRecoveryAt = $lastRecoveryAt->addSeconds((int) floor($recoveredSeconds / $staminaDto->getRecoveryRateMultiplier()));

            // 値を更新
            $staminaDto->setCurrentStamina($newStamina);
            $staminaDto->setLastRecoveryAt($newLastRecoveryAt->format('Y-m-d H:i:s'));

            return true;
        }

        return false;
    }

    /**
     * 次回スタミナ回復までの残り時間を取得（秒）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $type  スタミナタイプ
     * @return int|null 残り秒数（最大値の場合はnull）
     */
    public function getTimeUntilNextRecovery(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): ?int
    {
        $stamina = $this->staminaRepository->selectByPlayerAndType($sysPlayerId, $type);

        if ($stamina === null) {
            return null;
        }

        $maxStamina = $this->playerLevelService->getMaxStamina($sysPlayerId);

        // すでに最大値の場合は回復不要
        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return null;
        }

        $elapsedSeconds = ClockUtility::diffInSeconds($stamina->getLastRecoveryAt());
        $effectiveElapsedSeconds = $elapsedSeconds * $stamina->getRecoveryRateMultiplier();
        $remainingSeconds = self::RECOVERY_INTERVAL_SECONDS - ($effectiveElapsedSeconds % self::RECOVERY_INTERVAL_SECONDS);

        return (int) ceil($remainingSeconds / $stamina->getRecoveryRateMultiplier());
    }
}
