<?php

namespace NexusAuth\Contracts;

/**
 * DeviceModelInterface
 * 
 * デバイスモデルの抽象インターフェース
 * アプリケーション側のEloquentモデル（SysPlayerDevice等）が実装する
 */
interface DeviceModelInterface
{
    /**
     * デバイスID取得
     * 
     * @return int
     */
    public function getId(): int;

    /**
     * プレイヤーID取得
     * 
     * @return int
     */
    public function getPlayerId(): int;

    /**
     * デバイスUUID取得
     * 
     * @return string
     */
    public function getUuid(): string;

    /**
     * デバイス情報取得
     * 
     * @return array<string, mixed>|null
     */
    public function getDeviceInfo(): ?array;

    /**
     * 最終ログイン日時取得
     * 
     * @return string|null Y-m-d H:i:s形式
     */
    public function getLastLoginAt(): ?string;

    /**
     * 最終ログイン日時を更新
     * 
     * @return bool
     */
    public function updateLastLogin(): bool;

    /**
     * プレイヤーモデルを取得（リレーション）
     * 
     * @return PlayerModelInterface|null
     */
    public function getPlayer(): ?PlayerModelInterface;
}
