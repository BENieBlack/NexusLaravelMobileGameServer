<?php

namespace NexusPlayer\Services;

use NexusPlayer\DataTransferObjects\Player;
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
    public function findPlayerById(int $id): ?Player
    {
        return $this->playerRepository->selectById($id);
    }

    /**
     * My IDでプレイヤーを取得
     */
    public function findPlayerByMyId(string $myId): ?Player
    {
        return $this->playerRepository->selectByMyId($myId);
    }

    /**
     * デバイスUUIDでプレイヤーを取得
     */
    public function findPlayerByDeviceUuid(string $deviceUuid): ?Player
    {
        $device = $this->deviceRepository->selectByDeviceUuid($deviceUuid);

        if ($device === null) {
            return null;
        }

        return $this->playerRepository->selectById($device->getSysPlayerId());
    }

    /**
     * プレイヤー情報を更新
     */
    public function updatePlayer(Player $player): void
    {
        $this->playerRepository->persist($player);
    }

    /**
     * My IDの存在確認
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->playerRepository->existsByMyId($myId);
    }
}
