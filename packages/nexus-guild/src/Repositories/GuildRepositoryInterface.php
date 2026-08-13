<?php

namespace NexusGuild\Repositories;

use NexusGuild\Dto\GuildDto;

/**
 * GuildRepositoryInterface
 *
 * ギルドRepositoryのインターフェース
 */
interface GuildRepositoryInterface
{
    /**
     * IDでギルドを検索
     *
     * @param  int  $guildId  ギルドID
     */
    public function selectById(int $guildId): ?GuildDto;

    /**
     * ギルド名で検索
     *
     * @param  string  $name  ギルド名
     */
    public function selectByName(string $name): ?GuildDto;

    /**
     * 全ギルド一覧を取得
     *
     * @return array<GuildDto>
     */
    public function selectAll(): array;

    /**
     * ギルドを作成
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function insert(string $name, string $description, int $masterPlayerId): GuildDto;

    /**
     * ギルド情報を更新
     *
     * @param  GuildDto  $guildDto  更新するギルド
     * @param  array<string, mixed>  $data  更新データ
     * @return GuildDto 更新後のDTO
     */
    public function update(GuildDto $guildDto, array $data): GuildDto;

    /**
     * ギルドを削除
     *
     * @param  GuildDto  $guildDto  削除するギルド
     */
    public function delete(GuildDto $guildDto): void;

    /**
     * ギルド経験値を追加
     *
     * @param  GuildDto  $guildDto  対象ギルド
     * @param  int  $exp  追加経験値
     * @return GuildDto 更新後のDTO
     */
    public function addExp(GuildDto $guildDto, int $exp): GuildDto;

    /**
     * ギルドレベルを更新
     *
     * @param  GuildDto  $guildDto  対象ギルド
     * @param  int  $level  新しいレベル
     * @param  int  $exp  新しい経験値
     * @return GuildDto 更新後のDTO
     */
    public function updateLevel(GuildDto $guildDto, int $level, int $exp): GuildDto;
}
