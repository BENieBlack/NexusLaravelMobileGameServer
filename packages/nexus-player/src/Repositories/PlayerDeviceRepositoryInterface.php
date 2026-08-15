<?php

namespace NexusPlayer\Repositories;

use Illuminate\Support\Collection;
use NexusPlayer\DataTransferObjects\PlayerDevice;

/**
 * PlayerDeviceRepositoryInterface
 *
 * プレイヤーデバイスデータへのアクセスを抽象化
 */
interface PlayerDeviceRepositoryInterface
{
    /**
     * デバイスUUIDでデバイス情報を取得
     */
    public function selectByDeviceUuid(string $uuid): ?PlayerDevice;

    /**
     * プレイヤーIDでデバイス一覧を取得
     *
     * @return Collection<PlayerDevice>
     */
    public function selectByPlayerId(int $sysPlayerId): Collection;

    /**
     * デバイスを保存
     */
    public function persist(PlayerDevice $playerDevice): void;
}
