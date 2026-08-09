<?php

namespace NexusPlayer\Services;

use NexusPlayer\Repositories\PlayerLevelRepositoryInterface;
use NexusPlayer\Repositories\PlayerRepositoryInterface;

/**
 * PlayerLevelService
 *
 * プレイヤーレベル管理のビジネスロジックを担当するサービス
 */
class PlayerLevelService
{
    public function __construct(
        private readonly PlayerRepositoryInterface $playerRepository,
        private readonly PlayerLevelRepositoryInterface $levelRepository,
    ) {}

    /**
     * プレイヤーのレベル情報を取得
     *
     * @return array{level: int, exp: int, exp_to_next: int, max_stamina: int}
     *
     * @throws \Exception
     */
    public function getPlayerLevel(int $sysPlayerId): array
    {
        $player = $this->playerRepository->findById($sysPlayerId);

        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        $expToNext = $this->getExpToNextLevel($player->getLevel(), $player->getLevelExp());
        $maxStamina = $this->levelRepository->getMaxStaminaForLevel($player->getLevel()) ?? 50;

        return [
            'level' => $player->getLevel(),
            'exp' => $player->getLevelExp(),
            'exp_to_next' => $expToNext,
            'max_stamina' => $maxStamina,
        ];
    }

    /**
     * 経験値を加算し、レベルアップ処理を行う
     *
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
     * @throws \Exception
     */
    public function addExp(int $sysPlayerId, int $exp): array
    {
        $player = $this->playerRepository->findById($sysPlayerId);

        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        $beforeLevel = $player->getLevel();
        $beforeMaxStamina = $this->levelRepository->getMaxStaminaForLevel($beforeLevel) ?? 50;

        // 経験値を加算
        $newTotalExp = $player->getLevelExp() + $exp;

        // 新しいレベルを計算
        $afterLevel = $this->levelRepository->calculateLevelFromExp($newTotalExp);

        // 最大レベルを超えないように制限
        $maxLevel = $this->levelRepository->getMaxLevel();
        $afterLevel = min($afterLevel, $maxLevel);

        $isLeveledUp = ($afterLevel > $beforeLevel);

        // プレイヤー情報を更新
        $player->setLevel($afterLevel);
        $player->setLevelExp($newTotalExp);
        $this->playerRepository->save($player);

        // レベルアップした場合の最大スタミナを計算
        if ($isLeveledUp) {
            $afterMaxStamina = $this->levelRepository->getMaxStaminaForLevel($afterLevel) ?? $beforeMaxStamina;
        } else {
            $afterMaxStamina = $beforeMaxStamina;
        }

        // 次のレベルまでの経験値を計算
        $expToNext = $this->getExpToNextLevel($afterLevel, $newTotalExp);

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
     * @throws \Exception
     */
    public function getMaxStamina(int $sysPlayerId): int
    {
        $player = $this->playerRepository->findById($sysPlayerId);

        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        return $this->levelRepository->getMaxStaminaForLevel($player->getLevel()) ?? 50;
    }

    /**
     * 累積経験値から理論上のレベルを計算
     */
    public function calculateLevelFromExp(int $exp): int
    {
        return $this->levelRepository->calculateLevelFromExp($exp);
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     */
    private function getExpToNextLevel(int $currentLevel, int $currentExp): int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->levelRepository->findByLevel($nextLevel);

        if ($nextLevelData === null) {
            // 最大レベルに達している
            return 0;
        }

        return max(0, $nextLevelData['required_exp'] - $currentExp);
    }
}
