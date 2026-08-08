<?php

namespace NexusPlayer\Repositories;

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
     * 
     * @param string $uuid
     * @return PlayerDeviceDto|null
     */
    public function findByDeviceUuid(string $uuid): ?PlayerDeviceDto;

    /**
     * プレイヤーIDでデバイス一覧を取得
     * 
     * @param int $sysPlayerId
     * @return \Illuminate\Support\Collection<PlayerDeviceDto>
     */
    public function findByPlayerId(int $sysPlayerId): \Illuminate\Support\Collection;

    /**
     * デバイスを保存
     * 
     * @param PlayerDeviceDto $playerDeviceDto
     * @return void
     */
    public function save(PlayerDeviceDto $playerDeviceDto): void;
}
