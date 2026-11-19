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
     * ギルドのメンバー数を行ロック付きで数える
     *
     * 満員判定用。トランザクションの古いスナップショットではなく、
     * ロック取得後の最新の人数を返す。トランザクション内で呼ぶこと。
     *
     * @param  int  $guildId  ギルドID
     */
    public function countByGuildIdForUpdate(int $guildId): int;

    /**
     * プレイヤーの所属を、その人を軸にロックしたうえで最新の状態で検索
     *
     * 「この人をメンバーにしてよいか」の二重所属確認用。
     * 同じ人を別々のギルドが同時に承認しても、先に取った側だけが進められる。
     * トランザクション内で呼ぶこと。
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByPlayerIdForUpdate(int $playerId): ?GuildMember;

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
     * @param  GuildMember  $guildMember  対象メンバー
     * @param  string  $role  新しい役職
     * @return GuildMember 更新後のDTO
     */
    public function updateRole(GuildMember $guildMember, string $role): GuildMember;

    /**
     * ギルドメンバーを削除
     *
     * @param  GuildMember  $guildMember  削除するメンバー
     */
    public function delete(GuildMember $guildMember): void;

    /**
     * プレイヤーIDでメンバー情報を削除
     *
     * プレイヤーがギルドから脱退する際に使用
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function deleteByPlayerId(int $playerId): void;
}
