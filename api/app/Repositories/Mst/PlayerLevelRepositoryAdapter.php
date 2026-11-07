<?php

namespace App\Repositories\Mst;

use NexusLevel\Repositories\PlayerLevelRepositoryInterface;

/**
 * PlayerLevelRepositoryAdapter
 *
 * nexus-levelパッケージのPlayerLevelRepositoryInterfaceを実装し、
 * Application層のMstPlayerLevelRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、配列への詰め替えはこのアダプタが担う。
 */
class PlayerLevelRepositoryAdapter implements PlayerLevelRepositoryInterface
{
    public function __construct(
        private readonly MstPlayerLevelRepository $mstPlayerLevelRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByLevel(int $level): ?array
    {
        $mstPlayerLevel = $this->mstPlayerLevelRepository->selectByLevel($level);

        if ($mstPlayerLevel === null) {
            return null;
        }

        return [
            'level' => $mstPlayerLevel->getLevel(),
            'required_exp' => $mstPlayerLevel->getRequiredExp(),
            'max_stamina' => $mstPlayerLevel->getMaxStamina(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function calculateLevelFromExp(int $exp): int
    {
        return $this->mstPlayerLevelRepository->calculateLevelFromExp($exp);
    }

    /**
     * {@inheritDoc}
     */
    public function selectMaxLevel(): int
    {
        return $this->mstPlayerLevelRepository->selectMaxLevel();
    }

    /**
     * {@inheritDoc}
     */
    public function findMaxStaminaForLevel(int $level): ?int
    {
        return $this->mstPlayerLevelRepository->findMaxStaminaForLevel($level);
    }
}
