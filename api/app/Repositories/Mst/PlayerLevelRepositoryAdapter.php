<?php

namespace App\Repositories\Mst;

use NexusPlayer\Repositories\PlayerLevelRepositoryInterface;

/**
 * PlayerLevelRepositoryAdapter
 *
 * nexus-playerパッケージのPlayerLevelRepositoryInterfaceを実装し、
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
        $model = $this->mstPlayerLevelRepository->selectByLevel($level);

        if ($model === null) {
            return null;
        }

        return [
            'level' => $model->getLevel(),
            'required_exp' => $model->getRequiredExp(),
            'max_stamina' => $model->getMaxStamina(),
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
