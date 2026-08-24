<?php

namespace Nexus\Core\Contracts;

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
     * 最終ログイン日時をセット
     *
     * 属性を変更するだけでDBには反映しない。
     * 永続化はRepositoryのsetModel()経由で行うこと。
     */
    public function markLastLoginAt(): void;

    /**
     * プレイヤーモデルを取得（リレーション）
     * 
     * @return PlayerModelInterface|null
     */
    public function getPlayer(): ?PlayerModelInterface;
}
