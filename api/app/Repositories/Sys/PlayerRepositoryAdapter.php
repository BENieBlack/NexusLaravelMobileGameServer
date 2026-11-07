<?php

namespace App\Repositories\Sys;

use App\Adapters\Player\PlayerAdapter;
use App\Adapters\Player\PlayerVipAdapter;
use App\Models\Sys\SysPlayer;
use Nexus\Core\Contracts\PlayerModelInterface;
use Nexus\Core\DataTransferObjects\Player;
use Nexus\Core\Repositories\PlayerRepositoryInterface as PlayerRepoInterface;
use NexusVip\DataTransferObjects\PlayerVip;
use NexusVip\Repositories\PlayerVipRepositoryInterface;

/**
 * PlayerRepositoryAdapter
 *
 * nexus-core / nexus-vip パッケージのRepositoryInterfaceを実装し、
 * Application層のSysPlayerRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 * パッケージ側はApplication層のEloquent Modelに依存できないため、
 * 境界でDTOに詰め替える。
 */
class PlayerRepositoryAdapter implements PlayerRepoInterface, PlayerVipRepositoryInterface
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function insertPlayerAndCommit(): PlayerModelInterface
    {
        return $this->sysPlayerRepository->insertPlayerAndCommit();
    }

    /**
     * {@inheritDoc}
     */
    public function selectById(int $id): ?Player
    {
        $sysPlayer = $this->sysPlayerRepository->selectById($id);

        return $sysPlayer ? PlayerAdapter::toDto($sysPlayer) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByMyId(string $myId): ?Player
    {
        $sysPlayer = $this->sysPlayerRepository->selectByMyId($myId);

        return $sysPlayer ? PlayerAdapter::toDto($sysPlayer) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByUuid(string $uuid): ?Player
    {
        $sysPlayer = $this->sysPlayerRepository->selectByUuid($uuid);

        return $sysPlayer ? PlayerAdapter::toDto($sysPlayer) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Player $player): void
    {
        $sysPlayer = $this->sysPlayerRepository->selectById($player->getId());

        if ($sysPlayer === null) {
            return;
        }

        $sysPlayer->setName($player->getName());
        $sysPlayer->setLevel($player->getLevel());
        $sysPlayer->setLevelExp($player->getLevelExp());

        $this->sysPlayerRepository->setModel($sysPlayer);
    }

    /**
     * {@inheritDoc}
     */
    public function selectVipInfoById(int $sysPlayerId): ?PlayerVip
    {
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

        return $sysPlayer ? PlayerVipAdapter::toDto($sysPlayer) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persistVipInfo(PlayerVip $playerVip): void
    {
        $sysPlayer = $this->sysPlayerRepository->selectById($playerVip->getSysPlayerId());

        if ($sysPlayer === null) {
            return;
        }

        $sysPlayer->setVipPoint($playerVip->getVipPoint());
        $sysPlayer->setTotalPaidAmount($playerVip->getTotalPaidAmount());

        $this->sysPlayerRepository->setModel($sysPlayer);
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array
    {
        $sysPlayers = $this->sysPlayerRepository->selectByVipPointRange($minPoint, $maxPoint, $limit);

        return $sysPlayers->map(fn (SysPlayer $sysPlayer) => PlayerVipAdapter::toDto($sysPlayer))->all();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->sysPlayerRepository->existsByMyId($myId);
    }
}
