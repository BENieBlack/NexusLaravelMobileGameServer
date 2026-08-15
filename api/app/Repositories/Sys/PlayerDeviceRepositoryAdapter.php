<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerDevice;
use Illuminate\Support\Collection;
use NexusPlayer\DataTransferObjects\PlayerDevice;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;

/**
 * PlayerDeviceRepositoryAdapter
 *
 * nexus-playerパッケージのPlayerDeviceRepositoryInterfaceを実装し、
 * Application層のSysPlayerDeviceRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 * パッケージ側はApplication層のEloquent Modelに依存できないため、
 * 境界でDTOに詰め替える。
 */
class PlayerDeviceRepositoryAdapter implements PlayerDeviceRepositoryInterface
{
    public function __construct(
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByDeviceUuid(string $uuid): ?PlayerDevice
    {
        $model = $this->sysPlayerDeviceRepository->selectByDeviceId($uuid);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return Collection<int, PlayerDevice>
     */
    public function selectByPlayerId(int $sysPlayerId): Collection
    {
        $models = $this->sysPlayerDeviceRepository->selectListByPlayerId($sysPlayerId);

        return $models->map(fn (SysPlayerDevice $model) => $this->convertToDto($model));
    }

    /**
     * {@inheritDoc}
     */
    public function persist(PlayerDevice $playerDevice): void
    {
        // デバイスの更新はNexusPlayerパッケージでは現在未使用
        // 必要に応じて実装
    }

    /**
     * Eloquent ModelをDTOに変換
     */
    private function convertToDto(SysPlayerDevice $model): PlayerDevice
    {
        return new PlayerDevice(
            id: $model->getId(),
            sysPlayerId: $model->getSysPlayerId(),
            uuid: $model->getUuid(),
            deviceInfo: $model->getDeviceInfo(),
            lastLoginAt: $model->getLastLoginAt(),
            createdAt: $model->getCreatedAt(),
            updatedAt: $model->getUpdatedAt()
        );
    }
}
