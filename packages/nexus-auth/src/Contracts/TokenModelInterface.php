<?php

namespace NexusAuth\Contracts;

/**
 * TokenModelInterface
 * 
 * トークンモデルの抽象インターフェース
 * アプリケーション側のEloquentモデル（SysPlayerToken等）が実装する
 */
interface TokenModelInterface
{
    /**
     * トークンID取得
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
     * リフレッシュトークン取得
     * 
     * @return string
     */
    public function getRefreshToken(): string;

    /**
     * 有効期限取得
     * 
     * @return string Y-m-d H:i:s形式
     */
    public function getExpiresAt(): string;

    /**
     * トークンが有効期限切れかチェック
     * 
     * @return bool
     */
    public function isExpired(): bool;
}
