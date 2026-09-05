<?php

namespace NexusLevel\Services;

use Nexus\Core\DataTransferObjects\Player;
use Nexus\Core\Repositories\PlayerRepositoryInterface;
use NexusLevel\Contracts\PlayerLevelUpHandlerInterface;
use NexusLevel\Repositories\PlayerLevelRepositoryInterface;

/**
 * PlayerLevelService
 *
 * プレイヤーレベル管理のビジネスロジックを担当するサービス
 *
 * 経験値加算・レベル計算・最大レベル制限は _BaseLevelService の
 * テンプレートメソッドに任せ、ここではプレイヤー固有の取得・更新だけを実装する。
 * レベルアップ時のゲーム固有処理（スタミナ全回復など）は
 * PlayerLevelUpHandlerInterface の実装に委ねる。
 */
class PlayerLevelService extends _BaseLevelService
{
    /**
     * 最大スタミナのフォールバック値
     *
     * マスターに設定が無いレベルでもスタミナを0にしないための既定値
     */
    private const DEFAULT_MAX_STAMINA = 50;

    public function __construct(
        private readonly PlayerRepositoryInterface $playerRepository,
        private readonly PlayerLevelRepositoryInterface $levelRepository,
        private readonly ?PlayerLevelUpHandlerInterface $levelUpHandler = null,
    ) {}

    /**
     * プレイヤーのレベル情報を取得
     *
     * @return array{level: int, exp: int, exp_to_next: int, max_stamina: int}
     *
     * @throws \Exception プレイヤーが存在しない場合
     */
    public function findPlayerLevel(int $sysPlayerId): array
    {
        $player = $this->findPlayer($sysPlayerId);

        return [
            'level' => $player->getLevel(),
            'exp' => $player->getLevelExp(),
            'exp_to_next' => $this->calcExpToNextLevel(null, $player->getLevel(), $player->getLevelExp()),
            'max_stamina' => $this->findMaxStaminaForLevel($player->getLevel()),
        ];
    }

    /**
     * 経験値を加算し、レベルアップ処理を行う
     *
     * 基底クラスの結果に、プレイヤー固有の情報（次のレベルまでの経験値、
     * レベルアップ前後の最大スタミナ）を足して返す。
     *
     * @param  mixed  $id  プレイヤーID
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
    public function addExp(mixed $id, int $exp): array
    {
        $beforeMaxStamina = $this->findMaxStaminaForLevel($this->findPlayer((int) $id)->getLevel());

        $result = parent::addExp($id, $exp);

        $afterMaxStamina = $this->findMaxStaminaForLevel($result['after_level']);

        return [
            ...$result,
            'exp_to_next' => $this->calcExpToNextLevel(null, $result['after_level'], $result['total_exp']),
            'before_max_stamina' => $beforeMaxStamina,
            'after_max_stamina' => $afterMaxStamina,
        ];
    }

    /**
     * プレイヤーの最大スタミナを取得（レベルに基づく）
     *
     * @throws \Exception プレイヤーが存在しない場合
     */
    public function findMaxStamina(int $sysPlayerId): int
    {
        return $this->findMaxStaminaForLevel($this->findPlayer($sysPlayerId)->getLevel());
    }

    /**
     * 累積経験値から理論上のレベルを計算
     */
    public function calculateLevelFromExp(int $exp): int
    {
        return $this->levelRepository->calculateLevelFromExp($exp);
    }

    /**
     * {@inheritDoc}
     */
    public function calcExpToNextLevel(?string $rarity, int $currentLevel, int $currentExp): int
    {
        $nextLevelData = $this->levelRepository->selectByLevel($currentLevel + 1);

        if ($nextLevelData === null) {
            // 最大レベルに達している
            return 0;
        }

        return max(0, $nextLevelData['required_exp'] - $currentExp);
    }

    // ========================================
    // Abstract Methods の実装
    // ========================================

    /**
     * {@inheritDoc}
     */
    protected function findEntity(mixed $id): object
    {
        return $this->findPlayer((int) $id);
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveRarity(object $entity): ?string
    {
        // プレイヤーにはレアリティがない
        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveCurrentLevel(object $entity): int
    {
        /** @var Player $entity */
        return $entity->getLevel();
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveCurrentExp(object $entity): int
    {
        /** @var Player $entity */
        return $entity->getLevelExp();
    }

    /**
     * {@inheritDoc}
     */
    protected function calculateNewLevel(?string $rarity, int $totalExp): int
    {
        return $this->levelRepository->calculateLevelFromExp($totalExp);
    }

    /**
     * {@inheritDoc}
     */
    protected function findMaxLevel(?string $rarity): int
    {
        return $this->levelRepository->selectMaxLevel();
    }

    /**
     * {@inheritDoc}
     */
    protected function updateEntity(object $entity, int $level, int $exp): void
    {
        /** @var Player $entity */
        $entity->setLevel($level);
        $entity->setLevelExp($exp);

        $this->playerRepository->persist($entity);
    }

    /**
     * {@inheritDoc}
     *
     * レベルアップ時のゲーム固有処理はApplication層の実装に委ねる
     */
    protected function onLevelUp(object $entity, int $beforeLevel, int $afterLevel): void
    {
        /** @var Player $entity */
        $this->levelUpHandler?->handle($entity->getId(), $beforeLevel, $afterLevel);
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * プレイヤーを取得（存在しなければ例外）
     *
     * @throws \Exception プレイヤーが存在しない場合
     */
    private function findPlayer(int $sysPlayerId): Player
    {
        $player = $this->playerRepository->selectById($sysPlayerId);

        if ($player === null) {
            throw new \Exception("Player not found: {$sysPlayerId}");
        }

        return $player;
    }

    /**
     * レベルに対応する最大スタミナを取得
     */
    private function findMaxStaminaForLevel(int $level): int
    {
        return $this->levelRepository->findMaxStaminaForLevel($level) ?? self::DEFAULT_MAX_STAMINA;
    }
}
