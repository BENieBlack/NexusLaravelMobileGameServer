<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysGuildMember;
use Illuminate\Database\Eloquent\Collection;

/**
 * SysGuildMemberRepository
 *
 * ギルドメンバーのRepository実装
 *
 * @extends _BaseSysRepository<SysGuildMember>
 */
class SysGuildMemberRepository extends _BaseSysRepository
{
    protected string $modelClass = SysGuildMember::class;

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * IDでメンバーを検索（Model返却）
     *
     * @param  int  $memberId  メンバーID
     */
    public function selectById(int $memberId): ?SysGuildMember
    {
        return SysGuildMember::find($memberId);
    }

    /**
     * ギルドIDとプレイヤーIDでメンバーを検索（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?SysGuildMember
    {
        return SysGuildMember::where('sys_guild_id', $guildId)
            ->where('sys_player_id', $playerId)
            ->first();
    }

    /**
     * プレイヤーIDで所属ギルドメンバー情報を検索（Model返却）
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByPlayerId(int $playerId): ?SysGuildMember
    {
        return SysGuildMember::where('sys_player_id', $playerId)->first();
    }

    /**
     * ギルドIDでメンバー一覧を取得（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @return Collection<SysGuildMember>
     */
    public function selectByGuildId(int $guildId): Collection
    {
        return SysGuildMember::where('sys_guild_id', $guildId)->get();
    }

    /**
     * メンバーを作成（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     * @param  string  $role  役職
     */
    public function insertMember(int $guildId, int $playerId, string $role): SysGuildMember
    {
        $member = new SysGuildMember;
        $member->setSysGuildId($guildId);
        $member->setSysPlayerId($playerId);
        $member->setRole($role);
        $member->setJoinedAt(now()->format('Y-m-d H:i:s'));
        $member->exists = false;
        $this->setModel($member);

        // 呼び出し元が採番済みのメンバーIDを参照するため、ここでフラッシュする
        $this->flushQueue();

        return $member;
    }
}
