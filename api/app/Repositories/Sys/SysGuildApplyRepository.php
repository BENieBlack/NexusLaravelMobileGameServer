<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysGuildApply;
use App\Models\Sys\SysGuildMember;
use Illuminate\Database\Eloquent\Builder;
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

    /** @var list<string> 自分が出した申請。所属ギルド宛の分は applySelfScope で足す */
    protected array $selfScopeKeys = ['sys_player_id'];

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * 自分が出した申請と、自分が所属するギルド宛の申請を読む
     *
     * 未所属なら前者だけ、マスター/サブマスターなら後者も要る。
     * 承認・却下は他人が出した申請を書き換えるが、
     * 自分のギルド宛である限り「自分に関係する情報」として扱う。
     *
     * @param  Builder<SysGuildApply>  $query
     */
    protected function applySelfScope(Builder $query, int $sysPlayerId): void
    {
        $query->where(function (Builder $builder) use ($sysPlayerId) {
            $builder->where('sys_player_id', $sysPlayerId)
                ->orWhereIn('sys_guild_id', SysGuildMember::query()
                    ->where('sys_player_id', $sysPlayerId)
                    ->select('sys_guild_id'));
        });
    }

    /**
     * ギルドIDとプレイヤーIDで既存の申請を検索（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?SysGuildApply
    {
        if ($this->isSessionPlayer($playerId)) {
            /** @var SysGuildApply|null */
            return $this->queryOrMemory()->first(
                fn (SysGuildApply $apply) => $apply->getSysGuildId() === $guildId
                    && $apply->getSysPlayerId() === $playerId
                    && in_array($apply->getStatus(), [GuildApplyStatus::APPLIED, GuildApplyStatus::ACCEPTED], true)
            );
        }

        /** @var SysGuildApply|null */
        return $this->selectWithoutCache()
            ->where('sys_guild_id', $guildId)
            ->where('sys_player_id', $playerId)
            ->whereIn('status', [GuildApplyStatus::APPLIED, GuildApplyStatus::ACCEPTED])
            ->first();
    }

    /**
     * ギルドIDに関連する申請一覧を取得（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @return Collection<array-key, SysGuildApply>
     */
    public function selectAppliesByGuildId(int $guildId): Collection
    {
        if ($this->isOwnGuild($guildId)) {
            /** @var Collection<array-key, SysGuildApply> $applies */
            $applies = new Collection(
                $this->queryOrMemory()
                    ->filter(fn (SysGuildApply $apply) => $apply->getSysGuildId() === $guildId
                        && $apply->getStatus() === GuildApplyStatus::APPLIED)
                    ->values()
                    ->all()
            );

            return $applies;
        }

        return $this->selectWithoutCache()
            ->where('sys_guild_id', $guildId)
            ->where('status', GuildApplyStatus::APPLIED)
            ->get();
    }

    /**
     * プレイヤーIDに関連する申請一覧を取得（Model返却）
     *
     * @param  int  $playerId  プレイヤーID
     * @return Collection<array-key, SysGuildApply>
     */
    public function selectAppliesByPlayerId(int $playerId): Collection
    {
        if ($this->isSessionPlayer($playerId)) {
            /** @var Collection<array-key, SysGuildApply> $applies */
            $applies = new Collection(
                $this->queryOrMemory()
                    ->filter(fn (SysGuildApply $apply) => $apply->getSysPlayerId() === $playerId
                        && $apply->getStatus() === GuildApplyStatus::APPLIED)
                    ->values()
                    ->all()
            );

            return $applies;
        }

        return $this->selectWithoutCache()
            ->where('sys_player_id', $playerId)
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
     * ログイン中プレイヤーが所属するギルドかどうか
     */
    private function isOwnGuild(int $guildId): bool
    {
        if (! $this->hasSelfScope()) {
            return false;
        }

        return SysGuildMember::query()
            ->where('sys_player_id', static::getSysPlayerId())
            ->where('sys_guild_id', $guildId)
            ->exists();
    }
}
