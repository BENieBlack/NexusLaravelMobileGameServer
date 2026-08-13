<?php

namespace NexusPlayer\Repositories;

use Illuminate\Support\Collection;
use NexusPlayer\Dto\PlayerDeviceDto;

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
    public function selectByDeviceUuid(string $uuid): ?PlayerDeviceDto;

    /**
     * プレイヤーIDでデバイス一覧を取得
     *
     * @return Collection<PlayerDeviceDto>
     */
    public function selectByPlayerId(int $sysPlayerId): Collection;

    /**
     * デバイスを保存
     */
    public function persist(PlayerDeviceDto $playerDeviceDto): void;
}
