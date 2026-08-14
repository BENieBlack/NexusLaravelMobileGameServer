<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysGuildApply;
use Illuminate\Database\Eloquent\Collection;
use NexusGuild\Constants\GuildApplyStatus;

/**
 * SysGuildApplyRepository
 *
 * ギルド加入申請のRepository実装
 *
 * @extends _BaseSysRepository<SysGuildApply>
 */
class SysGuildApplyRepository extends _BaseSysRepository
{
    protected string $modelClass = SysGuildApply::class;

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * IDで申請を検索（Model返却）
     *
     * @param  int  $applyId  申請ID
     */
    public function selectById(int $applyId): ?SysGuildApply
    {
        return SysGuildApply::find($applyId);
    }

    /**
     * ギルドIDとプレイヤーIDで既存の申請を検索（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?SysGuildApply
    {
        return SysGuildApply::where('sys_guild_id', $guildId)
            ->where('sys_player_id', $playerId)
            ->whereIn('status', [GuildApplyStatus::APPLIED, GuildApplyStatus::ACCEPTED])
            ->first();
    }

    /**
     * ギルドIDに関連する申請一覧を取得（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @return Collection<SysGuildApply>
     */
    public function selectAppliesByGuildId(int $guildId): Collection
    {
        return SysGuildApply::where('sys_guild_id', $guildId)
            ->where('status', GuildApplyStatus::APPLIED)
            ->get();
    }

    /**
     * プレイヤーIDに関連する申請一覧を取得（Model返却）
     *
     * @param  int  $playerId  プレイヤーID
     * @return Collection<SysGuildApply>
     */
    public function selectAppliesByPlayerId(int $playerId): Collection
    {
        return SysGuildApply::where('sys_player_id', $playerId)
            ->where('status', GuildApplyStatus::APPLIED)
            ->get();
    }

    /**
     * 申請を作成（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function insertApply(int $guildId, int $playerId): SysGuildApply
    {
        $apply = new SysGuildApply;
        $apply->setSysGuildId($guildId);
        $apply->setSysPlayerId($playerId);
        $apply->setStatus(GuildApplyStatus::APPLIED);
        $apply->exists = false;
        $this->setModel($apply);

        // 呼び出し元が採番済みの申請IDを参照するため、ここでフラッシュする
        $this->flushQueue();

        return $apply;
    }

    /**
     * Modelを削除
     */
    public function deleteModel(mixed $model): void
    {
        $model->delete();
    }
}
