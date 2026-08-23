<?php

namespace NexusPlayer\Repositories;

use NexusPlayer\Contracts\PlayerModelInterface;
use NexusPlayer\DataTransferObjects\Player;

/**
 * PlayerRepositoryInterface
 *
 * プレイヤーデータへのアクセスを抽象化
 *
 * 参照・更新はDTO（DataTransferObjects\Player）でやりとりする。
 * 新規作成だけはIDと日時をDBが採番するため、実体であるモデル
 * （Contracts\PlayerModelInterface）をそのまま返す。
 */
interface PlayerRepositoryInterface
{
    /**
     * 新しいプレイヤーを作成して即座にコミット
     *
     * 採番されたIDが必要な呼び出し元（サインアップ）のために、
     * DTOではなくモデルを返す。
     */
    public function insertPlayerAndCommit(): PlayerModelInterface;

    /**
     * IDでプレイヤーを取得
     */
    public function selectById(int $id): ?Player;

    /**
     * My IDでプレイヤーを取得
     */
    public function selectByMyId(string $myId): ?Player;

    /**
     * UUIDでプレイヤーを取得
     */
    public function selectByUuid(string $uuid): ?Player;

    /**
     * プレイヤーを保存
     */
    public function persist(Player $player): void;

    /**
     * My IDの存在確認
     */
    public function existsByMyId(string $myId): bool;
}
