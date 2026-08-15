<?php

namespace NexusGuild\Repositories;

use NexusGuild\DataTransferObjects\GuildMember;

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
    public function selectById(int $memberId): ?GuildMember;

    /**
     * ギルドIDとプレイヤーIDでメンバーを検索
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildMember;

    /**
     * プレイヤーIDで所属ギルドメンバー情報を検索
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByPlayerId(int $playerId): ?GuildMember;

    /**
     * ギルドIDでメンバー一覧を取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildMember>
     */
    public function selectByGuildId(int $guildId): array;

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
    public function insert(int $guildId, int $playerId, string $role): GuildMember;

    /**
     * メンバーの役職を更新
     *
     * @param  GuildMember  $guildMemberDto  対象メンバー
     * @param  string  $role  新しい役職
     * @return GuildMember 更新後のDTO
     */
    public function updateRole(GuildMember $guildMemberDto, string $role): GuildMember;

    /**
     * ギルドメンバーを削除
     *
     * @param  GuildMember  $guildMemberDto  削除するメンバー
     */
    public function delete(GuildMember $guildMemberDto): void;

    /**
     * プレイヤーIDでメンバー情報を削除
     *
     * プレイヤーがギルドから脱退する際に使用
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function deleteByPlayerId(int $playerId): void;
}
