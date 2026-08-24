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
        $model = $this->sysPlayerRepository->selectById($id);

        return $model ? PlayerAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByMyId(string $myId): ?Player
    {
        $model = $this->sysPlayerRepository->selectByMyId($myId);

        return $model ? PlayerAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByUuid(string $uuid): ?Player
    {
        $model = $this->sysPlayerRepository->selectByUuid($uuid);

        return $model ? PlayerAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Player $player): void
    {
        $model = $this->sysPlayerRepository->selectById($player->getId());

        if ($model === null) {
            return;
        }

        $model->setName($player->getName());
        $model->setLevel($player->getLevel());
        $model->setLevelExp($player->getLevelExp());

        $this->sysPlayerRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function selectVipInfoById(int $sysPlayerId): ?PlayerVip
    {
        $model = $this->sysPlayerRepository->selectById($sysPlayerId);

        return $model ? PlayerVipAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persistVipInfo(PlayerVip $playerVip): void
    {
        $model = $this->sysPlayerRepository->selectById($playerVip->getSysPlayerId());

        if ($model === null) {
            return;
        }

        $model->setVipPoint($playerVip->getVipPoint());
        $model->setTotalPaidAmount($playerVip->getTotalPaidAmount());

        $this->sysPlayerRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array
    {
        $models = $this->sysPlayerRepository->selectByVipPointRange($minPoint, $maxPoint, $limit);

        return $models->map(fn (SysPlayer $model) => PlayerVipAdapter::toDto($model))->all();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->sysPlayerRepository->existsByMyId($myId);
    }
}
