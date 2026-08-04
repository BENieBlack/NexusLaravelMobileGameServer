<?php

namespace NexusAuth\Contracts;

/**
 * PlayerModelInterface
 * 
 * プレイヤーモデルの抽象インターフェース
 * アプリケーション側のEloquentモデル（SysPlayer等）が実装する
 */
interface PlayerModelInterface
{
    /**
     * プレイヤーID取得
     * 
     * @return int
     */
    public function getId(): int;

    /**
     * プレイヤーUUID取得
     * 
     * @return string
     */
    public function getUuid(): string;

    /**
     * 作成日時取得
     * 
     * @return string Y-m-d H:i:s形式
     */
    public function getCreatedAt(): string;
}
