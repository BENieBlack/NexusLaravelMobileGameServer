<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use NexusPlayer\Dto\PlayerDto;
use NexusPlayer\Repositories\PlayerRepositoryInterface as PlayerRepoInterface;
use NexusVip\DTOs\PlayerVipDto;
use NexusVip\Repositories\PlayerVipRepositoryInterface;

/**
 * PlayerRepositoryAdapter
 *
 * nexus-player / nexus-vip パッケージのRepositoryInterfaceを実装し、
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
    public function selectById(int $id): ?PlayerDto
    {
        $model = $this->sysPlayerRepository->selectById($id);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByMyId(string $myId): ?PlayerDto
    {
        $model = $this->sysPlayerRepository->selectByMyId($myId);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByUuid(string $uuid): ?PlayerDto
    {
        $model = $this->sysPlayerRepository->selectByUuid($uuid);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(PlayerDto $playerDto): void
    {
        $model = $this->sysPlayerRepository->selectById($playerDto->getId());

        if ($model === null) {
            return;
        }

        $model->setName($playerDto->getName());
        $model->setLevel($playerDto->getLevel());
        $model->setLevelExp($playerDto->getLevelExp());

        $this->sysPlayerRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function selectVipInfoById(int $sysPlayerId): ?PlayerVipDto
    {
        $model = $this->sysPlayerRepository->selectById($sysPlayerId);

        return $model ? $this->convertToPlayerVipDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persistVipInfo(PlayerVipDto $playerVipDto): void
    {
        $model = $this->sysPlayerRepository->selectById($playerVipDto->getSysPlayerId());

        if ($model === null) {
            return;
        }

        $model->setVipPoint($playerVipDto->getVipPoint());
        $model->setTotalPaidAmount($playerVipDto->getTotalPaidAmount());

        $this->sysPlayerRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array
    {
        $models = $this->sysPlayerRepository->selectByVipPointRange($minPoint, $maxPoint, $limit);

        return $models->map(fn (SysPlayer $model) => $this->convertToPlayerVipDto($model))->all();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->sysPlayerRepository->existsByMyId($myId);
    }

    /**
     * Eloquent ModelをPlayerDtoに変換
     */
    private function convertToDto(SysPlayer $model): PlayerDto
    {
        return new PlayerDto(
            id: $model->getId(),
            uuid: $model->getUuid(),
            myId: $model->getMyId(),
            name: $model->getName(),
            level: $model->getLevel(),
            levelExp: $model->getLevelExp(),
            createdAt: $model->getCreatedAt(),
            updatedAt: (string) $model->getAttribute('updated_at')
        );
    }

    /**
     * Eloquent ModelをPlayerVipDtoに変換
     */
    private function convertToPlayerVipDto(SysPlayer $model): PlayerVipDto
    {
        return new PlayerVipDto(
            sysPlayerId: $model->getId(),
            vipPoint: $model->getVipPoint(),
            totalPaidAmount: $model->getTotalPaidAmount()
        );
    }
}
