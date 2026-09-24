<?php

namespace Nexus\Core\Repositories;

use Nexus\Core\Contracts\DeviceModelInterface;

/**
 * PlayerDeviceRepositoryInterface
 *
 * プレイヤーのデバイス情報へのアクセスを抽象化
 *
 * デバイスはトークン発行やレスポンス組み立てまでモデルのまま持ち回るため、
 * プレイヤー本体（Repositories\PlayerRepositoryInterface）と違いDTOには
 * 詰め替えず、Contracts\DeviceModelInterface でやりとりする。
 */
interface PlayerDeviceRepositoryInterface
{
    /**
     * デバイスモデルを設定（バッチINSERT登録）
     */
    public function setModel(DeviceModelInterface $device): void;

    /**
     * デバイスUUIDでデバイス情報を取得
     */
    public function selectByDeviceId(string $deviceUuid): ?DeviceModelInterface;
}
