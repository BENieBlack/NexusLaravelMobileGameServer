<?php

namespace App\Domain\Stamina\Services;

use App\Domain\Player\Services\PlayerLevelService;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Models\Trx\TrxStamina;
use App\Repositories\Trx\TrxStaminaRepository;
use NexusStamina\Services\StaminaService as BaseStaminaService;
use NexusUtilities\ClockUtility;

/**
 * StaminaService
 *
 * パッケージ版のStaminaServiceのラッパー
 * Eloquent Modelを返すために変換処理を行う
 */
class StaminaService
{
    /**
     * スタミナ回復間隔（秒）
     */
    private const RECOVERY_INTERVAL_SECONDS = 300;

    public function __construct(
        private readonly TrxStaminaRepository $trxStaminaRepository,
        private readonly PlayerLevelService $playerLevelService,
        private readonly BaseStaminaService $baseStaminaService,
    ) {}

    /**
     * プレイヤーのスタミナ情報を取得（時間経過での自動回復を適用）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $type  スタミナタイプ
     */
    public function getStamina(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): ?TrxStamina
    {
        return $this->trxStaminaRepository->selectByType($type);
    }

    /**
     * 新しいプレイヤーのスタミナを初期化
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $initialStamina  初期スタミナ量
     * @param  string  $type  スタミナタイプ
     */
    public function initializeStamina(int $sysPlayerId, int $initialStamina, string $type = StaminaConst::TYPE_NORMAL): TrxStamina
    {
        $now = ClockUtility::now();

        $trxStamina = new TrxStamina([
            'sys_player_id' => $sysPlayerId,
            'type' => $type,
            'current_stamina' => $initialStamina,
            'recovery_rate_multiplier' => 1.00,
            'last_recovery_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $trxStamina->exists = false;
        $this->trxStaminaRepository->setModel($trxStamina);

        return $trxStamina;
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
        return $this->baseStaminaService->consumeStamina($sysPlayerId, $amount, $type);
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
        return $this->baseStaminaService->recoverStaminaByItem($sysPlayerId, $amount, $type);
    }

    /**
     * VIP特典などで回復速度倍率を更新
     *
     * @param  float  $multiplier  回復速度倍率
     * @param  string  $type  スタミナタイプ
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
     * 次回スタミナ回復までの残り時間を取得（秒）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $type  スタミナタイプ
     * @return int|null 残り秒数
     */
    public function getTimeUntilNextRecovery(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): ?int
    {
        return $this->baseStaminaService->getTimeUntilNextRecovery($sysPlayerId, $type);
    }

    /**
     * 最大スタミナまで完全回復するのに必要な時間を取得（秒）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $type  スタミナタイプ
     * @return int 必要な秒数
     */
    public function getTimeToFullRecovery(int $sysPlayerId, string $type = StaminaConst::TYPE_NORMAL): int
    {
        $stamina = $this->trxStaminaRepository->selectByType($type);

        if ($stamina === null) {
            return 0;
        }

        $maxStamina = $this->playerLevelService->getMaxStamina($sysPlayerId);

        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return 0;
        }

        $requiredPoints = $maxStamina - $stamina->getCurrentStamina();
        $baseSeconds = $requiredPoints * self::RECOVERY_INTERVAL_SECONDS;

        return (int) ceil($baseSeconds / $stamina->getRecoveryRateMultiplier());
    }
}
