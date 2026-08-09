<?php

namespace App\Domain\Player\Services;

use App\Domain\Stamina\Constants\StaminaConst;
use App\Models\Sys\SysPlayer;
use App\Models\Trx\TrxStamina;
use App\Repositories\Mst\MstPlayerLevelRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use NexusLevel\Services\_BaseLevelService;
use NexusUtilities\ClockUtility;

/**
 * PlayerLevelService
 *
 * プレイヤーレベル管理を担当するサービス
 * _BaseLevelServiceを継承して、プレイヤー固有のレベルアップ処理を実装
 *
 * レベルアップ仕様:
 * - レベルアップ時、現在スタミナは新しい最大値まで全回復（報酬）
 * - 経験値は累積方式（リセットされない）
 * - 最大レベル到達後は経験値が増えてもレベルアップしない
 */
class PlayerLevelService extends _BaseLevelService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly MstPlayerLevelRepository $mstPlayerLevelRepository,
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly TrxStaminaRepository $trxStaminaRepository,
    ) {}

    /**
     * プレイヤーのレベル情報を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return array{level: int, exp: int, exp_to_next: int, max_stamina: int}
     *
     * @throws \Exception プレイヤーが存在しない場合
     */
    public function getPlayerLevel(int $sysPlayerId): array
    {
        $player = $this->sysPlayerRepository->selectById($sysPlayerId);

        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        return [
            'level' => $player->getLevel(),
            'exp' => $player->getLevelExp(),
            'exp_to_next' => $player->getExpToNextLevel(),
            'max_stamina' => $player->getMaxStamina() ?? 50,
        ];
    }

    /**
     * 経験値を加算し、レベルアップ処理を行う
     *
     * レベルアップした場合:
     * - sys_player.levelとlevel_expを更新
     * - trx_stamina.current_staminaを新しい最大値に設定（全回復）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $exp  加算する経験値
     * @return array{
     *   is_leveled_up: bool,
     *   before_level: int,
     *   after_level: int,
     *   total_exp: int,
     *   exp_to_next: int,
     *   before_max_stamina: int,
     *   after_max_stamina: int
     * }
     *
     * @throws \Exception プレイヤーが存在しない場合
     */
    public function addExpWithStamina(int $sysPlayerId, int $exp): array
    {
        // 基底クラスのテンプレートメソッドを呼び出し
        $player = $this->sysPlayerRepository->selectById($sysPlayerId);
        $beforeMaxStamina = $player?->getMaxStamina() ?? 50;

        $result = parent::addExp($sysPlayerId, $exp);

        // プレイヤー固有の戻り値を追加
        $player = $this->sysPlayerRepository->selectById($sysPlayerId);
        $afterMaxStamina = $player?->getMaxStamina() ?? 50;

        return [
            ...$result,
            'exp_to_next' => $player?->getExpToNextLevel() ?? 0,
            'before_max_stamina' => $beforeMaxStamina,
            'after_max_stamina' => $afterMaxStamina,
        ];
    }

    /**
     * プレイヤーの最大スタミナを取得（レベルに基づく）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return int 最大スタミナ
     *
     * @throws \Exception プレイヤーが存在しない場合
     */
    public function getMaxStamina(int $sysPlayerId): int
    {
        $player = $this->sysPlayerRepository->selectById($sysPlayerId);

        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        return $player->getMaxStamina() ?? 50;
    }

    /**
     * 累積経験値から理論上のレベルを計算（純粋計算）
     *
     * @param  int  $exp  累積経験値
     * @return int レベル
     */
    public function calculateLevelFromExp(int $exp): int
    {
        return $this->mstPlayerLevelRepository->calculateLevelFromExp($exp);
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     *
     * @param  string|null  $rarity  レアリティ（プレイヤーの場合はnull）
     * @param  int  $currentLevel  現在のレベル
     * @param  int  $currentExp  現在の累積経験値
     * @return int 必要な経験値（最大レベルの場合は0）
     */
    public function getExpToNextLevel(?string $rarity, int $currentLevel, int $currentExp): int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->mstPlayerLevelRepository->selectByLevel($nextLevel);

        if ($nextLevelData === null) {
            // 最大レベルに達している
            return 0;
        }

        return max(0, $nextLevelData->getRequiredExp() - $currentExp);
    }

    // ========================================
    // Abstract Methods の実装
    // ========================================

    /**
     * {@inheritDoc}
     */
    protected function getEntity(mixed $id): object
    {
        $player = $this->sysPlayerRepository->selectById($id);

        if ($player === null) {
            throw new \Exception("Player not found: {$id}");
        }

        return $player;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRarity(object $entity): ?string
    {
        // プレイヤーにはレアリティがない
        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getCurrentLevel(object $entity): int
    {
        /** @var SysPlayer $entity */
        return $entity->getLevel();
    }

    /**
     * {@inheritDoc}
     */
    protected function getCurrentExp(object $entity): int
    {
        /** @var SysPlayer $entity */
        return $entity->getLevelExp();
    }

    /**
     * {@inheritDoc}
     */
    protected function calculateNewLevel(?string $rarity, int $totalExp): int
    {
        return $this->mstPlayerLevelRepository->calculateLevelFromExp($totalExp);
    }

    /**
     * {@inheritDoc}
     */
    protected function getMaxLevel(?string $rarity): int
    {
        return $this->mstPlayerLevelRepository->getMaxLevel();
    }

    /**
     * {@inheritDoc}
     */
    protected function updateEntity(object $entity, int $level, int $exp): void
    {
        /** @var SysPlayer $entity */
        $entity->setLevel($level);
        $entity->setLevelExp($exp);
        $this->sysPlayerRepository->setModel($entity);
    }

    /**
     * {@inheritDoc}
     *
     * プレイヤー固有の処理：レベルアップ時にスタミナを全回復
     */
    protected function onLevelUp(object $entity, int $beforeLevel, int $afterLevel): void
    {
        /** @var SysPlayer $entity */
        $sysPlayerId = $entity->getId();
        $newMaxStamina = $this->mstPlayerLevelRepository->getMaxStaminaForLevel($afterLevel)
            ?? ($entity->getMaxStamina() ?? 50);

        $this->refillStaminaOnLevelUp($sysPlayerId, $newMaxStamina);
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * レベルアップ時にスタミナを全回復（プライベートメソッド）
     *
     * 自然回復計算を行った後、最大スタミナ分を加算します。
     * 結果として最大スタミナを超過することができます。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $newMaxStamina  新しい最大スタミナ
     */
    private function refillStaminaOnLevelUp(int $sysPlayerId, int $newMaxStamina): void
    {
        $stamina = $this->trxStaminaRepository->selectByType(StaminaConst::TYPE_NORMAL);

        if ($stamina === null) {
            // スタミナレコードが存在しない場合は作成
            $now = ClockUtility::now();

            $trxStamina = new TrxStamina([
                'sys_player_id' => $sysPlayerId,
                'type' => StaminaConst::TYPE_NORMAL,
                'current_stamina' => $newMaxStamina,
                'recovery_rate_multiplier' => 1.00,
                'last_recovery_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $trxStamina->exists = false;
            $this->trxStaminaRepository->setModel($trxStamina);

            return;
        }

        // 自然回復計算を適用
        $this->applyAutoRecoveryForLevelUp($stamina, $newMaxStamina);

        // 最大スタミナ分を加算（最大値超過可能）
        $stamina->setCurrentStamina($stamina->getCurrentStamina() + $newMaxStamina);
        $this->trxStaminaRepository->setModel($stamina);
    }

    /**
     * レベルアップ時の自然回復計算（プライベートメソッド）
     *
     * StaminaServiceと同じロジックで自然回復を計算します。
     *
     * @param  TrxStamina  $stamina  スタミナモデル
     * @param  int  $maxStamina  プレイヤーの最大スタミナ
     */
    private function applyAutoRecoveryForLevelUp(TrxStamina $stamina, int $maxStamina): void
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
        $recoveredPoints = (int) floor($effectiveElapsedSeconds / 300);

        if ($recoveredPoints > 0) {
            // 新しいスタミナ値を計算
            $newStamina = min($stamina->getCurrentStamina() + $recoveredPoints, $maxStamina);

            // 次回回復基準時刻を計算（余剰秒数は切り捨て）
            $recoveredSeconds = $recoveredPoints * 300;
            $lastRecoveryAt = ClockUtility::parse($stamina->last_recovery_at);
            $newLastRecoveryAt = $lastRecoveryAt->addSeconds((int) floor($recoveredSeconds / $stamina->getRecoveryRateMultiplier()));

            // 値を更新
            $stamina->setCurrentStamina($newStamina);
            $stamina->setLastRecoveryAt($newLastRecoveryAt->format('Y-m-d H:i:s'));
        }
    }
}
