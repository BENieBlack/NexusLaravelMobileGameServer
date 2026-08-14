<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerDevice;
use Nexus\Core\Support\CustomCollection;
use NexusAuth\Contracts\DeviceRepositoryInterface;

/**
 * SysPlayerDeviceRepository
 *
 * プレイヤーデバイス情報のRepository実装
 *
 * 常にEloquent Modelを返す。DTOが必要な箇所は
 * PlayerDeviceRepositoryAdapterを経由すること。
 *
 * @extends _BaseSysRepository<SysPlayerDevice>
 */
class SysPlayerDeviceRepository extends _BaseSysRepository implements DeviceRepositoryInterface
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

        return new CustomCollection($sysPlayerDeviceCollection->all());
    }

    /**
     * デバイスを新規登録キューに積む
     *
     * 呼び出し元が採番済みのデバイスIDを参照するため、ここでフラッシュする
     *
     * @param  array<string, mixed>  $deviceInfo  デバイス情報
     */
    public function insertDevice(int $sysPlayerId, string $uuid, array $deviceInfo): SysPlayerDevice
    {
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayerId,
            'uuid' => $uuid,
            'device_info' => $deviceInfo,
            'last_login_at' => now(),
        ]);
        $sysPlayerDevice->exists = false;
        $this->setModel($sysPlayerDevice);
        $this->flushQueue();

        return $sysPlayerDevice;
    }
}
