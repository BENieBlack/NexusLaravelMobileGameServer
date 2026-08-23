<?php

namespace NexusPlayer\Repositories;

use NexusPlayer\DataTransferObjects\Player;

/**
 * PlayerRepositoryInterface
 *
 * プレイヤーデータへのアクセスを抽象化
 */
interface PlayerRepositoryInterface
{
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
