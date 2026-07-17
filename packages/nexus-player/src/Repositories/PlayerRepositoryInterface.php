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
     * 
     * @param int $id
     * @return PlayerDto|null
     */
    public function findById(int $id): ?PlayerDto;

    /**
     * My IDでプレイヤーを取得
     * 
     * @param string $myId
     * @return PlayerDto|null
     */
    public function findByMyId(string $myId): ?PlayerDto;

    /**
     * UUIDでプレイヤーを取得
     * 
     * @param string $uuid
     * @return PlayerDto|null
     */
    public function findByUuid(string $uuid): ?PlayerDto;

    /**
     * プレイヤーを保存
     * 
     * @param PlayerDto $player
     * @return void
     */
    public function save(PlayerDto $player): void;

    /**
     * My IDの存在確認
     * 
     * @param string $myId
     * @return bool
     */
    public function existsByMyId(string $myId): bool;
}
