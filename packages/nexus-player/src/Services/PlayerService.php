<?php

namespace NexusPlayer\Services;

use NexusPlayer\Dto\PlayerDto;
use NexusPlayer\Repositories\PlayerRepositoryInterface;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;

/**
 * PlayerService
 * 
 * プレイヤー管理のビジネスロジックを担当するサービス
 */
class PlayerService
{
    public function __construct(
        private readonly PlayerRepositoryInterface $playerRepository,
        private readonly PlayerDeviceRepositoryInterface $deviceRepository,
    ) {
    }

    /**
     * IDでプレイヤーを取得
     *
     * @param int $id
     * @return PlayerDto|null
     */
    public function getPlayerById(int $id): ?PlayerDto
    {
        return $this->playerRepository->findById($id);
    }

    /**
     * My IDでプレイヤーを取得
     *
     * @param string $myId
     * @return PlayerDto|null
     */
    public function getPlayerByMyId(string $myId): ?PlayerDto
    {
        return $this->playerRepository->findByMyId($myId);
    }

    /**
     * デバイスUUIDでプレイヤーを取得
     *
     * @param string $deviceUuid
     * @return PlayerDto|null
     */
    public function getPlayerByDeviceUuid(string $deviceUuid): ?PlayerDto
    {
        $device = $this->deviceRepository->findByDeviceUuid($deviceUuid);
        
        if ($device === null) {
            return null;
        }

        return $this->playerRepository->findById($device->getSysPlayerId());
    }

    /**
     * プレイヤー情報を更新
     *
     * @param PlayerDto $playerDto
     * @return void
     */
    public function updatePlayer(PlayerDto $playerDto): void
    {
        $this->playerRepository->save($playerDto);
    }

    /**
     * My IDの存在確認
     *
     * @param string $myId
     * @return bool
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->playerRepository->existsByMyId($myId);
    }
}
