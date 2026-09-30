<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerDevice;
use Nexus\Core\Repositories\PlayerDeviceRepositoryInterface;
use Nexus\Core\Support\CustomCollection;

/**
 * SysPlayerDeviceRepository
 *
 * プレイヤーデバイス情報のRepository実装
 *
 * デバイスはトークン発行やレスポンス組み立てまでModelのまま持ち回るため、
 * DTOには詰め替えずPlayerDeviceRepositoryInterfaceをそのまま実装する。
 *
 * @extends _BaseSysRepository<SysPlayerDevice>
 */
class SysPlayerDeviceRepository extends _BaseSysRepository implements PlayerDeviceRepositoryInterface
{
    protected string $modelClass = SysPlayerDevice::class;

    /**
     * デバイスUUID（device_id）からデバイス情報を検索（キャッシュを通さない）
     *
     * サインイン・サインアップの入口で呼ばれる。この時点では
     * まだログイン中プレイヤーが確定していないため、自分スコープを使えない。
     * 更新が要る場合は呼び出し側が setModel() すること。
     */
    public function selectByDeviceId(string $deviceId): ?SysPlayerDevice
    {
        // サインアップで登録した直後の端末は、まだDBに無い可能性がある。
        // キューに積んだ自分の行だけは先に見る
        $queued = $this->findCachedModels()->firstWhere('uuid', $deviceId);

        if ($queued !== null) {
            /** @var SysPlayerDevice */
            return $queued;
        }

        /** @var SysPlayerDevice|null */
        return $this->selectWithoutCache()->where('uuid', $deviceId)->first();
    }

    /**
     * プレイヤーIDからデバイス一覧を取得
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @return CustomCollection<int, SysPlayerDevice>
     */
    public function selectListByPlayerId(int $sysPlayerId): CustomCollection
    {
        if ($this->isSessionPlayer($sysPlayerId)) {
            /** @var CustomCollection<int, SysPlayerDevice> $devices */
            $devices = $this->queryOrMemory()
                ->filter(fn (SysPlayerDevice $device) => $device->getSysPlayerId() === $sysPlayerId)
                ->values();

            return $devices;
        }

        /** @var CustomCollection<int, SysPlayerDevice> $devices */
        $devices = new CustomCollection(
            $this->selectWithoutCache()->where('sys_player_id', $sysPlayerId)->get()->all()
        );

        return $devices;
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
