<?php

namespace App\Domain\Stamina\Services;

use App\Domain\Player\Services\LevelService;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Models\Trx\TrxStamina;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Persistence\ApiSession;
use App\Utilities\Clock;
use Carbon\CarbonImmutable;

/**
 * StaminaService
 * 
 * プレイヤーのスタミナ管理を担当するサービス
 * - 時間経過での自動回復
 * - スタミナ消費
 * - スタミナ回復アイテム使用
 * - オーバーフロー対応（最大値を超えた回復）
 */
class StaminaService
{
    /**
     * スタミナ回復間隔（秒）
     * デフォルト: 5分 = 300秒で1ポイント回復
     */
    private const RECOVERY_INTERVAL_SECONDS = 300;

    /**
     * コンストラクタ
     *
     * @param TrxStaminaRepository $trxStaminaRepository
     * @param LevelService $playerLevelService
     * @param ApiSession $apiSession
     */
    public function __construct(
        private readonly TrxStaminaRepository $trxStaminaRepository,
        private readonly LevelService $playerLevelService,
        private readonly ApiSession $apiSession
    ) {
    }

    /**
     * プレイヤーのスタミナ情報を取得（時間経過での自動回復を適用）
     * 
     * 最大スタミナはプレイヤーのレベルから自動取得されます
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $type スタミナタイプ
     * @return TrxStamina|null
     */
    public function getStamina(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): ?TrxStamina
    {
        $stamina = $this->trxStaminaRepository->selectByType($type);
        
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
     * @param int $sysPlayerId プレイヤーID
     * @param int $initialStamina 初期スタミナ量（通常は最大値）
     * @param string $type スタミナタイプ
     * @return TrxStamina
     */
    public function initializeStamina(int $sysPlayerId, int $initialStamina, string $type = StaminaConst::TYPE_NORMAL): TrxStamina
    {
        $now = Clock::now();

        $trxStamina = new TrxStamina([
            'sys_player_id' => $sysPlayerId,
            'type' => $type,
            'current_stamina' => $initialStamina,
            'overflow_stamina' => 0,
            'recovery_rate_multiplier' => 1.00,
            'last_recovery_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // exists = false なので INSERT として溜め込まれる
        $trxStamina->exists = false;

        $this->trxStaminaRepository->setModel($trxStamina);

        return $trxStamina;
    }

    /**
     * スタミナを消費
     * 
     * 最大スタミナはプレイヤーのレベルから自動取得されます
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $amount 消費量
     * @param string $type スタミナタイプ
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
        if (!$stamina->hasEnoughStamina($amount)) {
            return [
                'success' => false,
                'remaining' => $stamina->getTotalStamina(),
                'message' => 'Insufficient stamina',
            ];
        }

        // オーバーフローから優先的に消費
        if ($stamina->getOverflowStamina() >= $amount) {
            $stamina->setOverflowStamina($stamina->getOverflowStamina() - $amount);
        } else {
            $remaining = $amount - $stamina->getOverflowStamina();
            $stamina->setOverflowStamina(0);
            $stamina->setCurrentStamina($stamina->getCurrentStamina() - $remaining);
        }

        // Repository経由で保存
        $this->trxStaminaRepository->setModel($stamina);
        
        return [
            'success' => true,
            'remaining' => $stamina->getTotalStamina(),
            'message' => 'Stamina consumed successfully',
        ];
    }

    /**
     * スタミナ回復アイテム使用
     * 
     * 最大スタミナはプレイヤーのレベルから自動取得されます
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $amount 回復量
     * @param string $type スタミナタイプ
     * @return array{success: bool, total: int, overflow: int, message: string}
     */
    public function recoverStaminaByItem(int $sysPlayerId, int $amount, string $type = StaminaConst::TYPE_NORMAL): array
    {
        $stamina = $this->getStamina($sysPlayerId, $type);
        
        if ($stamina === null) {
            return [
                'success' => false,
                'total' => 0,
                'overflow' => 0,
                'message' => 'Stamina record not found',
            ];
        }

        // プレイヤーのレベルから最大スタミナを取得
        $maxStamina = $this->playerLevelService->getMaxStamina($sysPlayerId);

        // スタミナを回復（オーバーフロー対応）
        $newCurrent = $stamina->getCurrentStamina() + $amount;

        if ($newCurrent <= $maxStamina) {
            // 通常枠内での回復
            $stamina->setCurrentStamina($newCurrent);
        } else {
            // 通常枠を超えた分はオーバーフロー枠へ
            $overflow = $newCurrent - $maxStamina;
            $stamina->setCurrentStamina($maxStamina);
            $stamina->setOverflowStamina($stamina->getOverflowStamina() + $overflow);
        }

        // Repository経由で保存
        $this->trxStaminaRepository->setModel($stamina);
        
        return [
            'success' => true,
            'total' => $stamina->getTotalStamina(),
            'overflow' => $stamina->getOverflowStamina(),
            'message' => 'Stamina recovered successfully',
        ];
    }

    /**
     * VIP特典などで回復速度倍率を更新
     * 
     * @param float $multiplier 回復速度倍率（1.0 = 通常、1.5 = 1.5倍速等）
     * @param string $type スタミナタイプ
     * @return void
     */
    public function updateRecoveryRateMultiplier(float $multiplier, string $type = StaminaConst::TYPE_NORMAL): void
    {
        $stamina = $this->trxStaminaRepository->selectByType($type);

        if ($stamina) {
            $stamina->setRecoveryRateMultiplier($multiplier);
            $this->trxStaminaRepository->setModel($stamina);
        }
    }

    /**
     * 時間経過による自動回復を適用（プライベートメソッド）
     * 
     * @param TrxStamina $stamina スタミナモデル
     * @param int $maxStamina プレイヤーの最大スタミナ
     * @return void
     */
    private function applyAutoRecovery(TrxStamina $stamina, int $maxStamina): void
    {
        // すでに最大値の場合は回復不要
        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return;
        }

        $now = Clock::now();
        $lastRecoveryAt = CarbonImmutable::parse($stamina->last_recovery_at);
        
        // 経過秒数を計算
        $elapsedSeconds = $now->diffInSeconds($lastRecoveryAt);
        
        // 回復速度倍率を適用
        $effectiveElapsedSeconds = $elapsedSeconds * $stamina->getRecoveryRateMultiplier();
        
        // 回復ポイント数を計算
        $recoveredPoints = (int)floor($effectiveElapsedSeconds / self::RECOVERY_INTERVAL_SECONDS);
        
        if ($recoveredPoints > 0) {
            // 新しいスタミナ値を計算
            $newStamina = min($stamina->getCurrentStamina() + $recoveredPoints, $maxStamina);
            
            // 次回回復基準時刻を計算（余剰秒数は切り捨て）
            $recoveredSeconds = $recoveredPoints * self::RECOVERY_INTERVAL_SECONDS;
            $newLastRecoveryAt = $lastRecoveryAt->addSeconds((int)floor($recoveredSeconds / $stamina->getRecoveryRateMultiplier()));
            
            // 値を更新
            $stamina->setCurrentStamina($newStamina);
            $stamina->setLastRecoveryAt(\Carbon\Carbon::instance($newLastRecoveryAt));
            
            // Repository経由で保存
            $this->trxStaminaRepository->setModel($stamina);
        }
    }

    /**
     * 次回スタミナ回復までの残り時間を取得（秒）
     * 
     * 最大スタミナはプレイヤーのレベルから自動取得されます
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $type スタミナタイプ
     * @return int|null 残り秒数（最大値の場合はnull）
     */
    public function getTimeUntilNextRecovery(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): ?int
    {
        $stamina = $this->trxStaminaRepository->selectByType($type);
        
        if ($stamina === null) {
            return null;
        }

        // プレイヤーのレベルから最大スタミナを取得
        $maxStamina = $this->playerLevelService->getMaxStamina($sysPlayerId);

        // すでに最大値の場合は回復不要
        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return null;
        }

        $now = Clock::now();
        $lastRecoveryAt = CarbonImmutable::parse($stamina->last_recovery_at);
        $elapsedSeconds = $now->diffInSeconds($lastRecoveryAt);
        
        // 回復速度倍率を適用
        $effectiveElapsedSeconds = $elapsedSeconds * $stamina->getRecoveryRateMultiplier();
        
        // 次回回復までの残り秒数
        $remainingSeconds = self::RECOVERY_INTERVAL_SECONDS - ($effectiveElapsedSeconds % self::RECOVERY_INTERVAL_SECONDS);
        
        return (int)ceil($remainingSeconds / $stamina->getRecoveryRateMultiplier());
    }

    /**
     * 最大スタミナまで完全回復するのに必要な時間を取得（秒）
     * 
     * 最大スタミナはプレイヤーのレベルから自動取得されます
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $type スタミナタイプ
     * @return int 必要な秒数
     */
    public function getTimeToFullRecovery(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): int
    {
        $stamina = $this->trxStaminaRepository->selectByType($type);
        
        if ($stamina === null) {
            return 0;
        }

        // プレイヤーのレベルから最大スタミナを取得
        $maxStamina = $this->playerLevelService->getMaxStamina($sysPlayerId);

        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return 0;
        }

        $requiredPoints = $maxStamina - $stamina->getCurrentStamina();
        $baseSeconds = $requiredPoints * self::RECOVERY_INTERVAL_SECONDS;
        
        // 回復速度倍率を考慮
        return (int)ceil($baseSeconds / $stamina->getRecoveryRateMultiplier());
    }
}
