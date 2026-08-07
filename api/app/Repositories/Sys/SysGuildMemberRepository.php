<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildMemberAdapter;
use App\Models\Sys\SysGuildMember;
use NexusGuild\Dto\GuildMemberDto;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;

/**
 * SysGuildMemberRepository
 * 
 * ギルドメンバーのRepository実装
 * 
 * @extends _BaseSysRepository<SysGuildMember>
 */
class SysGuildMemberRepository extends _BaseSysRepository implements GuildMemberRepositoryInterface
{
    protected string $modelClass = SysGuildMember::class;

    /**
     * IDでギルドメンバーを検索（Interface実装）
     *
     * @param int $memberId メンバーID
     * @return GuildMemberDto|null
     */
    public function findById(int $memberId): ?GuildMemberDto
    {
        $model = $this->selectById($memberId);
        return $model ? GuildMemberAdapter::toDto($model) : null;
    }

    /**
     * ギルドIDとプレイヤーIDでメンバーを検索（Interface実装）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @return GuildMemberDto|null
     */
    public function findByGuildAndPlayer(int $guildId, int $playerId): ?GuildMemberDto
    {
        $model = $this->selectByGuildAndPlayer($guildId, $playerId);
        return $model ? GuildMemberAdapter::toDto($model) : null;
    }

    /**
     * プレイヤーIDで所属ギルドメンバー情報を検索（Interface実装）
     *
     * @param int $playerId プレイヤーID
     * @return GuildMemberDto|null
     */
    public function findByPlayerId(int $playerId): ?GuildMemberDto
    {
        $model = $this->selectByPlayerId($playerId);
        return $model ? GuildMemberAdapter::toDto($model) : null;
    }

    /**
     * ギルドIDでメンバー一覧を取得（Interface実装）
     *
     * @param int $guildId ギルドID
     * @return array<GuildMemberDto>
     */
    public function findByGuildId(int $guildId): array
    {
        $models = $this->selectByGuildId($guildId);
        return GuildMemberAdapter::toDtoArray($models);
    }

    /**
     * ギルドの現在のメンバー数を取得（Interface実装）
     *
     * @param int $guildId ギルドID
     * @return int
     */
    public function countByGuildId(int $guildId): int
    {
        return SysGuildMember::where('sys_guild_id', $guildId)->count();
    }

    /**
     * ギルドメンバーを作成（Interface実装）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @param string $role 役職
     * @return GuildMemberDto
     */
    public function create(int $guildId, int $playerId, string $role): GuildMemberDto
    {
        $model = $this->createMember($guildId, $playerId, $role);
        return GuildMemberAdapter::toDto($model);
    }

    /**
     * メンバーの役職を更新（Interface実装）
     *
     * @param GuildMemberDto $memberDto 対象メンバー
     * @param string $role 新しい役職
     * @return GuildMemberDto 更新後のDTO
     */
    public function updateRole(GuildMemberDto $memberDto, string $role): GuildMemberDto
    {
        $model = $this->selectById($memberDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Guild member not found');
        }

        $model->setRole($role);
        $this->setModel($model);

        return GuildMemberAdapter::toDto($model);
    }

    /**
     * ギルドメンバーを削除（Interface実装）
     *
     * @param GuildMemberDto $memberDto 削除するメンバー
     * @return void
     */
    public function delete(GuildMemberDto $memberDto): void
    {
        $model = $this->selectById($memberDto->getId());
        if ($model !== null) {
            $this->deleteModel($model);
        }
    }

    /**
     * プレイヤーIDでメンバー情報を削除（Interface実装）
     *
     * @param int $playerId プレイヤーID
     * @return void
     */
    public function deleteByPlayerId(int $playerId): void
    {
        SysGuildMember::where('sys_player_id', $playerId)->delete();
    }

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * IDでメンバーを検索（Model返却）
     *
     * @param int $memberId メンバーID
     * @return SysGuildMember|null
     */
    public function selectById(int $memberId): ?SysGuildMember
    {
        return SysGuildMember::find($memberId);
    }

    /**
     * ギルドIDとプレイヤーIDでメンバーを検索（Model返却）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @return SysGuildMember|null
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
     * @param int $playerId プレイヤーID
     * @return SysGuildMember|null
     */
    public function selectByPlayerId(int $playerId): ?SysGuildMember
    {
        return SysGuildMember::where('sys_player_id', $playerId)->first();
    }

    /**
     * ギルドIDでメンバー一覧を取得（Model返却）
     *
     * @param int $guildId ギルドID
     * @return \Illuminate\Database\Eloquent\Collection<SysGuildMember>
     */
    public function selectByGuildId(int $guildId): \Illuminate\Database\Eloquent\Collection
    {
        return SysGuildMember::where('sys_guild_id', $guildId)->get();
    }

    /**
     * メンバーを作成（Model返却）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @param string $role 役職
     * @return SysGuildMember
     */
    public function createMember(int $guildId, int $playerId, string $role): SysGuildMember
    {
        $member = new SysGuildMember();
        $member->setSysGuildId($guildId);
        $member->setSysPlayerId($playerId);
        $member->setRole($role);
        $member->setJoinedAt(now()->format('Y-m-d H:i:s'));
        $member->save();

        return $member;
    }

    /**
     * Modelを削除
     *
     * @param mixed $model
     * @return void
     */
    public function deleteModel(mixed $model): void
    {
        $model->delete();
    }
}
