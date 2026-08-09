<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerDevice;
use Illuminate\Support\Collection;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use Nexus\Core\Support\CustomCollection;
use NexusPlayer\Dto\PlayerDeviceDto;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;

/**
 * SysPlayerDeviceRepository
 *
 * プレイヤーデバイス情報のRepository実装
 *
 * @extends _BaseSysRepository<SysPlayerDevice>
 */
class SysPlayerDeviceRepository extends _BaseSysRepository implements DeviceRepositoryInterface, PlayerDeviceRepositoryInterface
{
    protected string $modelClass = SysPlayerDevice::class;

    /**
     * デバイスUUID（device_id）からデバイス情報を検索
     * メモリキャッシュから検索、なければDBから取得
     */
    public function selectByDeviceId(string $deviceId): ?SysPlayerDevice
    {
        // メモリキャッシュから検索
        $sysPlayerDevice = $this->getModels()->firstWhere('uuid', $deviceId);

        if ($sysPlayerDevice !== null) {
            /** @var SysPlayerDevice */
            return $sysPlayerDevice;
        }

        // DBから取得してメモリキャッシュに保存
        $sysPlayerDevice = $this->modelClass::where('uuid', $deviceId)->first();

        if ($sysPlayerDevice !== null) {
            $this->setModel($sysPlayerDevice);
        }

        return $sysPlayerDevice;
    }

    /**
     * プレイヤーIDからデバイス一覧を取得
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @return CustomCollection<int, SysPlayerDevice>
     */
    public function selectListByPlayerId(int $sysPlayerId): CustomCollection
    {
        // メモリキャッシュから検索
        $sysPlayerDeviceCollection = $this->getModels()->where('sys_player_id', $sysPlayerId);

        if ($sysPlayerDeviceCollection->isNotEmpty()) {
            return $sysPlayerDeviceCollection->values();
        }

        // DBから取得してメモリキャッシュに保存
        $sysPlayerDeviceCollection = $this->modelClass::where('sys_player_id', $sysPlayerId)->get();

        foreach ($sysPlayerDeviceCollection as $sysPlayerDevice) {
            $this->setModel($sysPlayerDevice);
        }

        return $sysPlayerDeviceCollection;
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerDeviceRepositoryInterface実装
     */
    public function findByDeviceUuid(string $uuid): ?PlayerDeviceDto
    {
        $model = $this->selectByDeviceId($uuid);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerDeviceRepositoryInterface実装
     */
    public function findByPlayerId(int $sysPlayerId): Collection
    {
        $models = $this->selectListByPlayerId($sysPlayerId);

        return $models->map(fn ($model) => $this->convertToDto($model));
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerDeviceRepositoryInterface実装
     */
    public function save(PlayerDeviceDto $playerDeviceDto): void
    {
        // デバイスの更新はNexusPlayerパッケージでは現在未使用
        // 必要に応じて実装
    }

    /**
     * Eloquent ModelをDTOに変換
     */
    private function convertToDto(SysPlayerDevice $model): PlayerDeviceDto
    {
        return new PlayerDeviceDto(
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
