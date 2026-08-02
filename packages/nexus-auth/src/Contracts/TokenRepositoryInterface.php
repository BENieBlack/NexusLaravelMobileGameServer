<?php

namespace NexusAuth\Contracts;

/**
 * TokenRepositoryInterface
 * 
 * トークンリポジトリの抽象インターフェース
 */
interface TokenRepositoryInterface
{
    /**
     * トークンモデルを設定（バッチINSERT登録）
     * 
     * @param TokenModelInterface $token
     * @return void
     */
    public function setModel(TokenModelInterface $token): void;

    /**
     * リフレッシュトークンで検索
     * 
     * @param string $refreshToken
     * @return TokenModelInterface|null
     */
    public function selectByRefreshToken(string $refreshToken): ?TokenModelInterface;

    /**
     * IDでトークンを削除
     * 
     * @param int $tokenId
     * @return int 削除件数
     */
    public function deleteById(int $tokenId): int;

    /**
     * プレイヤーIDでトークンを削除
     * 
     * @param int $playerId
     * @return int 削除件数
     */
    public function deleteByPlayerId(int $playerId): int;
}
