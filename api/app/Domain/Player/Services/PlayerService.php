<?php

namespace App\Domain\Player\Services;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerRepository;
use NexusUnitOfWork\Contracts\QueryManagerInterface;

/**
 * PlayerService
 *
 * プレイヤー作成と管理を担当するサービス
 */
class PlayerService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository
    ) {}

    /**
     * 新しいプレイヤーを作成
     *
     * プレイヤーは即座にコミットしてIDを取得し、デバイスはバッチINSERTで保存します。
     * このメソッド内でexecAllQuery()を実行して、デバイスのIDを取得します。
     *
     * @param  string  $deviceId  デバイスUUID
     * @param  array<string, mixed>|null  $deviceInfo  デバイス情報（JSON）
     * @return array{sys_player: SysPlayer, sys_player_device: SysPlayerDevice}
     */
    public function createPlayer(
        string $deviceId,
        ?array $deviceInfo = null
    ): array {
        // 1. プレイヤーを作成して即座にコミット（IDを取得）
        $sysPlayer = $this->sysPlayerRepository->insertPlayerAndCommit();

        // 2. デバイスを作成してメモリに登録（バッチINSERT対象）
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => $deviceId,
            'device_info' => $deviceInfo,
            'last_login_at' => now(),
        ]);

        $this->sysPlayerDeviceRepository->setModel($sysPlayerDevice);

        // 3. バッチINSERTを実行してデバイスのIDを取得
        app(QueryManagerInterface::class)->flush();

        return [
            'sys_player' => $sysPlayer,
            'sys_player_device' => $sysPlayerDevice,
        ];
    }

    /**
     * 既存デバイスからプレイヤーとデバイス情報を取得
     *
     * @param  string  $deviceUuid  デバイスUUID
     */
    public function selectByDeviceId(string $deviceUuid): ?SysPlayerDevice
    {
        return $this->sysPlayerDeviceRepository->selectByDeviceId($deviceUuid);
    }

    /**
     * IDでプレイヤーを検索
     *
     * @param  int  $id  プレイヤーID
     */
    public function selectById(int $id): ?SysPlayer
    {
        return $this->sysPlayerRepository->selectById($id);
    }

    /**
     * デバイスの最終ログイン日時を更新
     */
    public function updateLastLogin(SysPlayerDevice $sysPlayerDevice): bool
    {
        return $sysPlayerDevice->updateLastLogin();
    }
}
