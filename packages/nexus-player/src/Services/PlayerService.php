<?php

namespace NexusPlayer\Services;

use NexusPlayer\Dto\PlayerDto;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;
use NexusPlayer\Repositories\PlayerRepositoryInterface;

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
    ) {}

    /**
     * IDでプレイヤーを取得
     */
    public function getPlayerById(int $id): ?PlayerDto
    {
        return $this->playerRepository->findById($id);
    }

    /**
     * My IDでプレイヤーを取得
     */
    public function getPlayerByMyId(string $myId): ?PlayerDto
    {
        return $this->playerRepository->findByMyId($myId);
    }

    /**
     * デバイスUUIDでプレイヤーを取得
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
     */
    public function updatePlayer(PlayerDto $playerDto): void
    {
        $this->playerRepository->save($playerDto);
    }

    /**
     * My IDの存在確認
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->playerRepository->existsByMyId($myId);
    }
}
