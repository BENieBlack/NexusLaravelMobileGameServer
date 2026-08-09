<?php

namespace NexusGuild\Repositories;

use NexusGuild\Dto\GuildMemberDto;

/**
 * GuildMemberRepositoryInterface
 *
 * ギルドメンバーRepositoryのインターフェース
 */
interface GuildMemberRepositoryInterface
{
    /**
     * IDでギルドメンバーを検索
     *
     * @param  int  $memberId  メンバーID
     */
    public function findById(int $memberId): ?GuildMemberDto;

    /**
     * ギルドIDとプレイヤーIDでメンバーを検索
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function findByGuildAndPlayer(int $guildId, int $playerId): ?GuildMemberDto;

    /**
     * プレイヤーIDで所属ギルドメンバー情報を検索
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function findByPlayerId(int $playerId): ?GuildMemberDto;

    /**
     * ギルドIDでメンバー一覧を取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildMemberDto>
     */
    public function findByGuildId(int $guildId): array;

    /**
     * ギルドの現在のメンバー数を取得
     *
     * @param  int  $guildId  ギルドID
     */
    public function countByGuildId(int $guildId): int;

    /**
     * ギルドメンバーを作成
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     * @param  string  $role  役職
     */
    public function create(int $guildId, int $playerId, string $role): GuildMemberDto;

    /**
     * メンバーの役職を更新
     *
     * @param  GuildMemberDto  $guildMemberDto  対象メンバー
     * @param  string  $role  新しい役職
     * @return GuildMemberDto 更新後のDTO
     */
    public function updateRole(GuildMemberDto $guildMemberDto, string $role): GuildMemberDto;

    /**
     * ギルドメンバーを削除
     *
     * @param  GuildMemberDto  $guildMemberDto  削除するメンバー
     */
    public function delete(GuildMemberDto $guildMemberDto): void;

    /**
     * プレイヤーIDでメンバー情報を削除
     *
     * プレイヤーがギルドから脱退する際に使用
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function deleteByPlayerId(int $playerId): void;
}
