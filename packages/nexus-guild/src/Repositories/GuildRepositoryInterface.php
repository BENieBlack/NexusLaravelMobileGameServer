<?php

namespace NexusGuild\Repositories;

use NexusGuild\DataTransferObjects\Guild;

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
    public function selectById(int $guildId): ?Guild;

    /**
     * ギルド名で検索
     *
     * @param  string  $name  ギルド名
     */
    public function selectByName(string $name): ?Guild;

    /**
     * 全ギルド一覧を取得
     *
     * @return array<Guild>
     */
    public function selectAll(): array;

    /**
     * ギルドを作成
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function insert(string $name, string $description, int $masterPlayerId): Guild;

    /**
     * ギルド情報を更新
     *
     * @param  Guild  $guildDto  更新するギルド
     * @param  array<string, mixed>  $data  更新データ
     * @return Guild 更新後のDTO
     */
    public function update(Guild $guildDto, array $data): Guild;

    /**
     * ギルドを削除
     *
     * @param  Guild  $guildDto  削除するギルド
     */
    public function delete(Guild $guildDto): void;

    /**
     * ギルド経験値を追加
     *
     * @param  Guild  $guildDto  対象ギルド
     * @param  int  $exp  追加経験値
     * @return Guild 更新後のDTO
     */
    public function addExp(Guild $guildDto, int $exp): Guild;

    /**
     * ギルドレベルを更新
     *
     * @param  Guild  $guildDto  対象ギルド
     * @param  int  $level  新しいレベル
     * @param  int  $exp  新しい経験値
     * @return Guild 更新後のDTO
     */
    public function updateLevel(Guild $guildDto, int $level, int $exp): Guild;
}
