<?php

namespace NexusAuth\Contracts;

/**
 * DeviceRepositoryInterface
 * 
 * デバイスリポジトリの抽象インターフェース
 */
interface DeviceRepositoryInterface
{
    /**
     * デバイスモデルを設定（バッチINSERT登録）
     * 
     * @param DeviceModelInterface $device
     * @return void
     */
    public function setModel(DeviceModelInterface $device): void;

    /**
     * デバイスUUIDでデバイス情報を取得
     * 
     * @param string $deviceUuid
     * @return DeviceModelInterface|null
     */
    public function selectByDeviceId(string $deviceUuid): ?DeviceModelInterface;
}
