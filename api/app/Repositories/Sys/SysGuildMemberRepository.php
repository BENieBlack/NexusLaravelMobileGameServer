<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysGuildMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Nexus\Core\Utilities\ClockUtility;

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
     * 自分の行と、自分が所属するギルドのメンバー行を読む
     *
     * マスターが同じギルドのメンバーを見る・増やすのは
     * 「自分に関係する情報」の範囲として扱う。
     *
     * @param  Builder<SysGuildMember>  $query
     */
    protected function applySelectScope(Builder $query, int $sysPlayerId): void
    {
        $query->where(function (Builder $builder) use ($sysPlayerId) {
            $builder->where('sys_player_id', $sysPlayerId)
                ->orWhereIn('sys_guild_id', $this->ownGuildIdQuery($sysPlayerId));
        });
    }

    /**
     * ギルドIDとプレイヤーIDでメンバーを検索（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?SysGuildMember
    {
        if ($this->isOwnGuild($guildId)) {
            /** @var SysGuildMember|null */
            return $this->queryOrMemory()
                ->first(fn (SysGuildMember $member) => $member->getSysGuildId() === $guildId
                    && $member->getSysPlayerId() === $playerId);
        }

        /** @var SysGuildMember|null */
        return $this->selectWithoutCache()
            ->where('sys_guild_id', $guildId)
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
        if ($this->isSessionPlayer($playerId)) {
            /** @var SysGuildMember|null */
            return $this->queryOrMemory()
                ->first(fn (SysGuildMember $member) => $member->getSysPlayerId() === $playerId);
        }

        /** @var SysGuildMember|null */
        return $this->selectWithoutCache()->where('sys_player_id', $playerId)->first();
    }

    /**
     * ギルドのメンバー数を FOR UPDATE で数える
     *
     * 普通の count は、トランザクションの開始時点の古いスナップショットを読みうる
     * （REPEATABLE READ）。ロックを待った後に古い人数で判定すると定員を超えるので、
     * 行ロック付きの読み取り（常に最新）で行を読んで数える。
     * ギルド行が mutex なので、ロック自体は冗長（current read にするために FOR UPDATE を使う）。
     * ギルドは最大でも数十人なので、行を読んでもコストは小さい。
     *
     * @param  int  $guildId  ギルドID
     */
    public function countByGuildIdForUpdate(int $guildId): int
    {
        return count($this->selectForUpdate()->where('sys_guild_id', $guildId)->pluck('id')->all());
    }

    /**
     * プレイヤーの所属を FOR UPDATE で読み直す（二重所属の確認用）
     *
     * 該当行が無いとき、FOR UPDATE はギャップロックになり、2 つのトランザクションが
     * 同時に取れてしまう（その後の insert で両方が入る）。
     * そのため「この人を誰がメンバーにするか」の直列化は、呼び出し側が
     * プレイヤー行（sys_player）のロックで行い、ここは最新の所属を読む役。
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function selectByPlayerIdForUpdate(int $playerId): ?SysGuildMember
    {
        /** @var SysGuildMember|null */
        return $this->selectForUpdate()->where('sys_player_id', $playerId)->first();
    }

    /**
     * ギルドIDでメンバー一覧を取得（Model返却）
     *
     * @param  int  $guildId  ギルドID
     * @return Collection<array-key, SysGuildMember>
     */
    public function selectByGuildId(int $guildId): Collection
    {
        if ($this->isOwnGuild($guildId)) {
            /** @var Collection<array-key, SysGuildMember> $members */
            $members = new Collection(
                $this->queryOrMemory()
                    ->filter(fn (SysGuildMember $member) => $member->getSysGuildId() === $guildId)
                    ->values()
                    ->all()
            );

            return $members;
        }

        return $this->selectWithoutCache()->where('sys_guild_id', $guildId)->get();
    }

    /**
     * ログイン中プレイヤーが所属するギルドかどうか
     */
    private function isOwnGuild(int $guildId): bool
    {
        if (! $this->hasSelfScope()) {
            return false;
        }

        $sysPlayerId = static::getSysPlayerId();

        return $this->queryOrMemory()->contains(
            fn (SysGuildMember $member) => $member->getSysPlayerId() === $sysPlayerId
                && $member->getSysGuildId() === $guildId
        );
    }

    /**
     * ログイン中プレイヤーが所属するギルドIDを引くサブクエリ
     *
     * @return Builder<SysGuildMember>
     */
    private function ownGuildIdQuery(int $sysPlayerId): Builder
    {
        return SysGuildMember::query()
            ->where('sys_player_id', $sysPlayerId)
            ->select('sys_guild_id');
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
        $member->setJoinedAt(ClockUtility::now()->format('Y-m-d H:i:s'));
        $member->exists = false;
        $this->setModel($member);

        // 呼び出し元が採番済みのメンバーIDを参照するため、ここでフラッシュする
        $this->flushQueue();

        return $member;
    }
}
