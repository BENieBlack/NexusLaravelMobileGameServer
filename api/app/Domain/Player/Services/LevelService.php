<?php

namespace App\Domain\Player\Services;

use App\Models\Sys\SysPlayer;
use App\Repositories\Mst\MstPlayerLevelRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Utilities\ApiSession;

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
     * @param int $sysPlayerId プレイヤーID
     * @param int $newMaxStamina 新しい最大スタミナ
     * @return void
     */
    private function refillStaminaOnLevelUp(int $sysPlayerId, int $newMaxStamina): void
    {
        $stamina = $this->trxStaminaRepository->find();
        
        if ($stamina === null) {
            // スタミナレコードが存在しない場合は作成
            $now = \App\Utilities\Clock::now();

            $trxStamina = new \App\Models\Trx\TrxStamina([
                'sys_player_id' => $sysPlayerId,
                'current_stamina' => $newMaxStamina,
                'overflow_stamina' => 0,
                'recovery_rate_multiplier' => 1.00,
                'last_recovery_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $trxStamina->exists = false;
            $this->trxStaminaRepository->setModel($trxStamina);
            return;
        }
        
        // 通常枠を最大値まで全回復（オーバーフロー枠はそのまま）
        $stamina->setCurrentStamina($newMaxStamina);
        $stamina->setLastRecoveryAt(\Carbon\Carbon::instance(\App\Utilities\Clock::now()));
        $this->trxStaminaRepository->setModel($stamina);
    }
}
