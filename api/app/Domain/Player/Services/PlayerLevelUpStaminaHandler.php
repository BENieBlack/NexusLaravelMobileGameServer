<?php

namespace App\Domain\Player\Services;

use App\Domain\Stamina\Constants\StaminaConst;
use App\Models\Trx\TrxStamina;
use App\Repositories\Mst\MstPlayerLevelRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use Nexus\Core\Utilities\ClockUtility;
use NexusPlayer\Contracts\PlayerLevelUpHandlerInterface;

/**
 * PlayerLevelUpStaminaHandler
 *
 * レベルアップ時にスタミナを全回復するゲーム固有処理。
 *
 * レベルアップ自体の計算は NexusPlayer\Services\PlayerLevelService が担い、
 * その報酬にあたるこの処理だけをApplication層に置く。
 * （パッケージ側にスタミナ操作を持たせると nexus-player と nexus-stamina が
 * 相互に依存してしまうため）
 */
class PlayerLevelUpStaminaHandler implements PlayerLevelUpHandlerInterface
{
    /**
     * スタミナ回復間隔（秒）
     */
    private const RECOVERY_INTERVAL_SECONDS = 300;

    /**
     * 最大スタミナのフォールバック値
     */
    private const DEFAULT_MAX_STAMINA = 50;

    public function __construct(
        private readonly MstPlayerLevelRepository $mstPlayerLevelRepository,
        private readonly TrxStaminaRepository $trxStaminaRepository,
    ) {}

    /**
     * {@inheritDoc}
     *
     * 自然回復計算を行った後、新しい最大スタミナ分を加算する。
     * 結果として最大スタミナを超過することができる（レベルアップ報酬のため）。
     */
    public function handle(int $sysPlayerId, int $beforeLevel, int $afterLevel): void
    {
        $newMaxStamina = $this->mstPlayerLevelRepository->findMaxStaminaForLevel($afterLevel)
            ?? self::DEFAULT_MAX_STAMINA;

        $stamina = $this->trxStaminaRepository->selectByType(StaminaConst::TYPE_NORMAL);

        if ($stamina === null) {
            $this->createStamina($sysPlayerId, $newMaxStamina);

            return;
        }

        // 自然回復計算を適用
        $this->applyAutoRecovery($stamina, $newMaxStamina);

        // 最大スタミナ分を加算（最大値超過可能）
        $stamina->setCurrentStamina($stamina->getCurrentStamina() + $newMaxStamina);
        $this->trxStaminaRepository->setModel($stamina);
    }

    /**
     * スタミナレコードが存在しない場合に作成する
     */
    private function createStamina(int $sysPlayerId, int $newMaxStamina): void
    {
        $trxStamina = new TrxStamina([
            'sys_player_id' => $sysPlayerId,
            'type' => StaminaConst::TYPE_NORMAL,
            'current_stamina' => $newMaxStamina,
            'recovery_rate_multiplier' => 1.00,
            'last_recovery_at' => ClockUtility::now(),
        ]);

        $trxStamina->exists = false;
        $this->trxStaminaRepository->setModel($trxStamina);
    }

    /**
     * レベルアップ時の自然回復計算
     *
     * StaminaServiceと同じロジックで、加算前の時点までの自然回復を反映する。
     *
     * @param  TrxStamina  $stamina  スタミナモデル
     * @param  int  $maxStamina  プレイヤーの最大スタミナ
     */
    private function applyAutoRecovery(TrxStamina $stamina, int $maxStamina): void
    {
        // すでに最大値の場合は回復不要
        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return;
        }

        // 経過秒数を計算
        $elapsedSeconds = ClockUtility::diffInSeconds($stamina->last_recovery_at);

        // 回復速度倍率を適用（5分 = 300秒で1ポイント回復）
        $effectiveElapsedSeconds = $elapsedSeconds * $stamina->getRecoveryRateMultiplier();

        // 回復ポイント数を計算
        $recoveredPoints = (int) floor($effectiveElapsedSeconds / self::RECOVERY_INTERVAL_SECONDS);

        if ($recoveredPoints <= 0) {
            return;
        }

        // 新しいスタミナ値を計算
        $newStamina = min($stamina->getCurrentStamina() + $recoveredPoints, $maxStamina);

        // 次回回復基準時刻を計算（余剰秒数は切り捨て）
        $recoveredSeconds = $recoveredPoints * self::RECOVERY_INTERVAL_SECONDS;
        $lastRecoveryAt = ClockUtility::parse($stamina->last_recovery_at);
        $newLastRecoveryAt = $lastRecoveryAt->addSeconds((int) floor($recoveredSeconds / $stamina->getRecoveryRateMultiplier()));

        // 値を更新
        $stamina->setCurrentStamina($newStamina);
        $stamina->setLastRecoveryAt($newLastRecoveryAt->format('Y-m-d H:i:s'));
    }
}
