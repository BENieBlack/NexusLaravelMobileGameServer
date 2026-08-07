<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildApplyAdapter;
use App\Models\Sys\SysGuildApply;
use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\Dto\GuildApplyDto;
use NexusGuild\Repositories\GuildApplyRepositoryInterface;

/**
 * SysGuildApplyRepository
 * 
 * ギルド加入申請のRepository実装
 * 
 * @extends _BaseSysRepository<SysGuildApply>
 */
class SysGuildApplyRepository extends _BaseSysRepository implements GuildApplyRepositoryInterface
{
    protected string $modelClass = SysGuildApply::class;

    /**
     * IDでギルド加入申請を検索（Interface実装）
     *
     * @param int $applyId 申請ID
     * @return GuildApplyDto|null
     */
    public function findById(int $applyId): ?GuildApplyDto
    {
        $model = $this->selectById($applyId);
        return $model ? GuildApplyAdapter::toDto($model) : null;
    }

    /**
     * ギルドIDとプレイヤーIDで既存の申請を検索（Interface実装）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @return GuildApplyDto|null
     */
    public function findByGuildAndPlayer(int $guildId, int $playerId): ?GuildApplyDto
    {
        $model = $this->selectByGuildAndPlayer($guildId, $playerId);
        return $model ? GuildApplyAdapter::toDto($model) : null;
    }

    /**
     * ギルドIDに関連する申請一覧を取得（Interface実装）
     *
     * @param int $guildId ギルドID
     * @return array<GuildApplyDto>
     */
    public function findAppliesByGuildId(int $guildId): array
    {
        $models = $this->selectAppliesByGuildId($guildId);
        return GuildApplyAdapter::toDtoArray($models);
    }

    /**
     * プレイヤーIDに関連する申請一覧を取得（Interface実装）
     *
     * @param int $playerId プレイヤーID
     * @return array<GuildApplyDto>
     */
    public function findAppliesByPlayerId(int $playerId): array
    {
        $models = $this->selectAppliesByPlayerId($playerId);
        return GuildApplyAdapter::toDtoArray($models);
    }

    /**
     * ギルド加入申請を作成（Interface実装）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @return GuildApplyDto
     */
    public function create(int $guildId, int $playerId): GuildApplyDto
    {
        $model = $this->createApply($guildId, $playerId);
        return GuildApplyAdapter::toDto($model);
    }

    /**
     * ギルド加入申請を承認（Interface実装）
     *
     * @param GuildApplyDto $applyDto 承認する申請
     * @return GuildApplyDto 承認後のDTO
     */
    public function accept(GuildApplyDto $applyDto): GuildApplyDto
    {
        $model = $this->selectById($applyDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Guild apply not found');
        }

        $model->setStatus(GuildApplyStatus::ACCEPTED);
        $this->setModel($model);

        return GuildApplyAdapter::toDto($model);
    }

    /**
     * ギルド加入申請を却下（Interface実装）
     *
     * @param GuildApplyDto $applyDto 却下する申請
     * @return GuildApplyDto 却下後のDTO
     */
    public function reject(GuildApplyDto $applyDto): GuildApplyDto
    {
        $model = $this->selectById($applyDto->getId());
        if ($model === null) {
            throw new \RuntimeException('Guild apply not found');
        }

        $model->setStatus(GuildApplyStatus::REJECTED);
        $this->setModel($model);

        return GuildApplyAdapter::toDto($model);
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

    /**
     * プレイヤーIDで申請を削除（Interface実装）
     *
     * @param int $playerId プレイヤーID
     * @return void
     */
    public function deleteByPlayerId(int $playerId): void
    {
        SysGuildApply::where('sys_player_id', $playerId)->delete();
    }

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * IDで申請を検索（Model返却）
     *
     * @param int $applyId 申請ID
     * @return SysGuildApply|null
     */
    public function selectById(int $applyId): ?SysGuildApply
    {
        return SysGuildApply::find($applyId);
    }

    /**
     * ギルドIDとプレイヤーIDで既存の申請を検索（Model返却）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @return SysGuildApply|null
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
     * @param int $guildId ギルドID
     * @return \Illuminate\Database\Eloquent\Collection<SysGuildApply>
     */
    public function selectAppliesByGuildId(int $guildId): \Illuminate\Database\Eloquent\Collection
    {
        return SysGuildApply::where('sys_guild_id', $guildId)
            ->where('status', GuildApplyStatus::APPLIED)
            ->get();
    }

    /**
     * プレイヤーIDに関連する申請一覧を取得（Model返却）
     *
     * @param int $playerId プレイヤーID
     * @return \Illuminate\Database\Eloquent\Collection<SysGuildApply>
     */
    public function selectAppliesByPlayerId(int $playerId): \Illuminate\Database\Eloquent\Collection
    {
        return SysGuildApply::where('sys_player_id', $playerId)
            ->where('status', GuildApplyStatus::APPLIED)
            ->get();
    }

    /**
     * 申請を作成（Model返却）
     *
     * @param int $guildId ギルドID
     * @param int $playerId プレイヤーID
     * @return SysGuildApply
     */
    public function createApply(int $guildId, int $playerId): SysGuildApply
    {
        $apply = new SysGuildApply();
        $apply->setSysGuildId($guildId);
        $apply->setSysPlayerId($playerId);
        $apply->setStatus(GuildApplyStatus::APPLIED);
        $apply->save();

        return $apply;
    }
}
