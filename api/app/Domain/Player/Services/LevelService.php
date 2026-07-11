<?php

namespace App\Domain\Player\Services;

use App\Domain\Stamina\Constants\StaminaConst;
use App\Models\Sys\SysPlayer;
use App\Repositories\Mst\MstPlayerLevelRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Persistence\ApiSession;

/**
 * LevelService
 * 
 * プレイヤーレベル管理を担当するサービス
 * - 経験値加算とレベルアップ処理
 * - レベルに応じた最大スタミナの取得
 * - 累積経験値からのレベル計算
 * 
 * レベルアップ仕様:
 * - レベルアップ時、現在スタミナは新しい最大値まで全回復（報酬）
 * - 経験値は累積方式（リセットされない）
 * - 最大レベル到達後は経験値が増えてもレベルアップしない
 */
class LevelService
{
    /**
     * コンストラクタ
     *
     * @param MstPlayerLevelRepository $mstPlayerLevelRepository
     * @param SysPlayerRepository $sysPlayerRepository
     * @param TrxStaminaRepository $trxStaminaRepository
     */
    public function __construct(
        private readonly MstPlayerLevelRepository $mstPlayerLevelRepository,
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly TrxStaminaRepository $trxStaminaRepository,
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * プレイヤーのレベル情報を取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @return array{level: int, exp: int, exp_to_next: int, max_stamina: int}
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
     * 命名規約:
     * - Bool値: is_* / has_* プレフィックス
     * - 変更前後: before_* / after_*
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $exp 加算する経験値
     * @return array{
     *   is_leveled_up: bool,
     *   before_level: int,
     *   after_level: int,
     *   total_exp: int,
     *   exp_to_next: int,
     *   before_max_stamina: int,
     *   after_max_stamina: int
     * }
     * @throws \Exception プレイヤーが存在しない場合
     */
    public function addExp(int $sysPlayerId, int $exp): array
    {
        $player = $this->sysPlayerRepository->selectById($sysPlayerId);
        
        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        $beforeLevel = $player->getLevel();
        $beforeMaxStamina = $player->getMaxStamina() ?? 50;
        
        // 経験値を加算
        $newTotalExp = $player->getLevelExp() + $exp;
        
        // 新しいレベルを計算
        $afterLevel = $this->mstPlayerLevelRepository->calculateLevelFromExp($newTotalExp);
        
        // 最大レベルを超えないように制限
        $maxLevel = $this->mstPlayerLevelRepository->getMaxLevel();
        $afterLevel = min($afterLevel, $maxLevel);
        
        $isLeveledUp = ($afterLevel > $beforeLevel);
        
        // プレイヤー情報を更新（Unit of Work パターン使用）
        $player->setLevel($afterLevel);
        $player->setLevelExp($newTotalExp);
        $this->sysPlayerRepository->setModel($player);
        
        // レベルアップした場合、スタミナを全回復
        if ($isLeveledUp) {
            $afterMaxStamina = $this->mstPlayerLevelRepository->getMaxStaminaForLevel($afterLevel) ?? $beforeMaxStamina;
            $this->refillStaminaOnLevelUp($sysPlayerId, $afterMaxStamina);
        } else {
            $afterMaxStamina = $beforeMaxStamina;
        }
        
        // 次のレベルまでの経験値を計算
        $expToNext = $player->getExpToNextLevel();
        
        return [
            'is_leveled_up' => $isLeveledUp,
            'before_level' => $beforeLevel,
            'after_level' => $afterLevel,
            'total_exp' => $newTotalExp,
            'exp_to_next' => $expToNext,
            'before_max_stamina' => $beforeMaxStamina,
            'after_max_stamina' => $afterMaxStamina,
        ];
    }

    /**
     * プレイヤーの最大スタミナを取得（レベルに基づく）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @return int 最大スタミナ
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
     * @param int $exp 累積経験値
     * @return int レベル
     */
    public function calculateLevelFromExp(int $exp): int
    {
        return $this->mstPlayerLevelRepository->calculateLevelFromExp($exp);
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     * 
     * @param int $currentLevel 現在のレベル
     * @param int $currentExp 現在の累積経験値
     * @return int 必要な経験値（最大レベルの場合は0）
     */
    public function getExpToNextLevel(int $currentLevel, int $currentExp): int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->mstPlayerLevelRepository->selectByLevel($nextLevel);
        
        if ($nextLevelData === null) {
            // 最大レベルに達している
            return 0;
        }
        
        return max(0, $nextLevelData->getRequiredExp() - $currentExp);
    }

    /**
     * レベルアップ時にスタミナを全回復（プライベートメソッド）
     * 
     * 自然回復計算を行った後、最大スタミナ分を加算します。
     * 結果として最大スタミナを超過することができます。
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $newMaxStamina 新しい最大スタミナ
     * @return void
     */
    private function refillStaminaOnLevelUp(int $sysPlayerId, int $newMaxStamina): void
    {
        $stamina = $this->trxStaminaRepository->selectByType(StaminaConst::TYPE_NORMAL);
        
        if ($stamina === null) {
            // スタミナレコードが存在しない場合は作成
            $now = \App\Utilities\ClockUtility::now();

            $trxStamina = new \App\Models\Trx\TrxStamina([
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
     * @param \App\Models\Trx\TrxStamina $stamina スタミナモデル
     * @param int $maxStamina プレイヤーの最大スタミナ
     * @return void
     */
    private function applyAutoRecoveryForLevelUp(\App\Models\Trx\TrxStamina $stamina, int $maxStamina): void
    {
        // すでに最大値の場合は回復不要
        if ($stamina->isCurrentStaminaFull($maxStamina)) {
            return;
        }

        $now = \App\Utilities\ClockUtility::now();
        $lastRecoveryAt = \Carbon\CarbonImmutable::parse($stamina->last_recovery_at);
        
        // 経過秒数を計算
        $elapsedSeconds = $now->diffInSeconds($lastRecoveryAt);
        
        // 回復速度倍率を適用（5分 = 300秒で1ポイント回復）
        $effectiveElapsedSeconds = $elapsedSeconds * $stamina->getRecoveryRateMultiplier();
        
        // 回復ポイント数を計算
        $recoveredPoints = (int)floor($effectiveElapsedSeconds / 300);
        
        if ($recoveredPoints > 0) {
            // 新しいスタミナ値を計算
            $newStamina = min($stamina->getCurrentStamina() + $recoveredPoints, $maxStamina);
            
            // 次回回復基準時刻を計算（余剰秒数は切り捨て）
            $recoveredSeconds = $recoveredPoints * 300;
            $newLastRecoveryAt = $lastRecoveryAt->addSeconds((int)floor($recoveredSeconds / $stamina->getRecoveryRateMultiplier()));
            
            // 値を更新
            $stamina->setCurrentStamina($newStamina);
            $stamina->setLastRecoveryAt(\Carbon\Carbon::instance($newLastRecoveryAt));
        }
    }
}
