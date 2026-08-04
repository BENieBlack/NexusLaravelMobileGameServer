<?php

namespace NexusAuth\Contracts;

/**
 * PlayerRepositoryInterface
 * 
 * プレイヤーリポジトリの抽象インターフェース
 * アプリケーション側でEloquentモデルに対応する実装を提供する
 */
interface PlayerRepositoryInterface
{
    /**
     * 新しいプレイヤーを作成して即座にコミット
     * 
     * @return PlayerModelInterface
     */
    public function createPlayerAndCommit(): PlayerModelInterface;

    /**
     * IDでプレイヤーを検索
     * 
     * @param int $id
     * @return PlayerModelInterface|null
     */
    public function selectById(int $id): ?PlayerModelInterface;
}
