<?php

namespace NexusPlayer\Repositories;

use NexusPlayer\Dto\PlayerDto;

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
    public function selectById(int $id): ?PlayerDto;

    /**
     * My IDでプレイヤーを取得
     */
    public function selectByMyId(string $myId): ?PlayerDto;

    /**
     * UUIDでプレイヤーを取得
     */
    public function selectByUuid(string $uuid): ?PlayerDto;

    /**
     * プレイヤーを保存
     */
    public function persist(PlayerDto $playerDto): void;

    /**
     * My IDの存在確認
     */
    public function existsByMyId(string $myId): bool;
}
