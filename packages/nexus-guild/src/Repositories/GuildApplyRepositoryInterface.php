<?php

namespace NexusGuild\Repositories;

use NexusGuild\DataTransferObjects\GuildApply;

/**
 * GuildApplyRepositoryInterface
 *
 * ギルド加入申請Repositoryのインターフェース
 */
interface GuildApplyRepositoryInterface
{
    /**
     * IDでギルド加入申請を検索
     *
     * @param  int  $applyId  申請ID
     */
    public function selectById(int $applyId): ?GuildApply;

    /**
     * ギルドIDとプレイヤーIDで既存の申請を検索
     *
     * Applied または Accepted のステータスのみを検索対象とする
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildApply;

    /**
     * ギルドIDに関連する申請一覧を取得
     *
     * statusがAppliedのものを取得
     *
     * @param  int  $guildId  ギルドID
     * @return array<GuildApply>
     */
    public function selectAppliesByGuildId(int $guildId): array;

    /**
     * プレイヤーIDに関連する申請一覧を取得
     *
     * statusがAppliedのものを取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return array<GuildApply>
     */
    public function selectAppliesByPlayerId(int $playerId): array;

    /**
     * ギルド加入申請を作成
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function insert(int $guildId, int $playerId): GuildApply;

    /**
     * ギルド加入申請を承認
     *
     * @param  GuildApply  $guildApply  承認する申請
     * @return GuildApply 承認後のDTO
     */
    public function accept(GuildApply $guildApply): GuildApply;

    /**
     * ギルド加入申請を却下
     *
     * @param  GuildApply  $guildApply  却下する申請
     * @return GuildApply 却下後のDTO
     */
    public function reject(GuildApply $guildApply): GuildApply;

    /**
     * プレイヤーの全申請を削除（論理削除）
     *
     * プレイヤーがギルドから脱退する際に使用
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function deleteByPlayerId(int $playerId): void;
}
