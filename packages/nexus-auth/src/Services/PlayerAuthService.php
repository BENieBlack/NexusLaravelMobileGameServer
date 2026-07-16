<?php

namespace NexusAuth\Services;

use NexusAuth\Contracts\DeviceModelInterface;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use NexusAuth\Contracts\PlayerModelInterface;
use NexusAuth\Contracts\PlayerRepositoryInterface;

/**
 * PlayerAuthService
 * 
 * プレイヤー作成と管理を担当するサービス
 * デバイスベース認証のプレイヤー管理を提供
 */
class PlayerAuthService
{
    /**
     * コンストラクタ
     *
     * @param PlayerRepositoryInterface $playerRepository
     * @param DeviceRepositoryInterface $deviceRepository
     */
    public function __construct(
        private readonly PlayerRepositoryInterface $playerRepository,
        private readonly DeviceRepositoryInterface $deviceRepository,
    ) {
    }

    /**
     * 新しいプレイヤーを作成
     * 
     * プレイヤーとデバイスを作成してコミット
     *
     * @param string $deviceId デバイスUUID
     * @param array<string, mixed>|null $deviceInfo デバイス情報（JSON）
     * @return PlayerModelInterface
     */
    public function createPlayer(
        string $deviceId,
        ?array $deviceInfo = null
    ): PlayerModelInterface {
        // 1. プレイヤーを作成して即座にコミット（IDを取得）
        $player = $this->playerRepository->createPlayerAndCommit();

        // 2. デバイスを作成
        // Note: アプリケーション層でSysPlayerDeviceモデルを作成する必要がある
        // この処理はアプリケーション層のSignUpUseCaseで行う
        
        return $player;
    }

    /**
     * 既存デバイスからプレイヤーとデバイス情報を取得
     *
     * @param string $deviceUuid デバイスUUID
     * @return DeviceModelInterface|null
     */
    public function selectByDeviceId(string $deviceUuid): ?DeviceModelInterface
    {
        return $this->deviceRepository->selectByDeviceId($deviceUuid);
    }

    /**
     * IDでプレイヤーを検索
     *
     * @param int $id プレイヤーID
     * @return PlayerModelInterface|null
     */
    public function selectById(int $id): ?PlayerModelInterface
    {
        return $this->playerRepository->selectById($id);
    }

    /**
     * デバイスの最終ログイン日時を更新
     *
     * @param string $deviceUuid デバイスUUID
     * @return bool
     */
    public function updateLastLogin(string $deviceUuid): bool
    {
        $device = $this->deviceRepository->selectByDeviceId($deviceUuid);
        
        if ($device === null) {
            return false;
        }
        
        return $device->updateLastLogin();
    }
}
