<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysGuild;
use App\Models\Sys\SysGuildMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use NexusGuild\Constants\GuildRole;

/**
 * SysGuildRepository
 *
 * ギルド情報のRepository実装
 *
 * @extends _BaseSysRepository<SysGuild>
 */
class SysGuildRepository extends _BaseSysRepository
{
    protected string $modelClass = SysGuild::class;

    public function __construct(
        private readonly SysGuildMemberRepository $sysGuildMemberRepository,
    ) {}

    // ========================================
    // Application層専用メソッド（Model返却）
    // ========================================

    /**
     * 自分が所属するギルドだけを読む
     *
     * sys_guild にはプレイヤーを指す列が無いので、
     * 所属メンバー行を経由して絞る。
     *
     * @param  Builder<SysGuild>  $query
     */
    protected function applySelectScope(Builder $query, int $sysPlayerId): void
    {
        $query->whereIn('id', SysGuildMember::query()
            ->where('sys_player_id', $sysPlayerId)
            ->select('sys_guild_id'));
    }

    /**
     * ギルド名で検索（キャッシュを通さない）
     *
     * 名前の重複チェックに使う。他人のギルドも対象なので更新はできない。
     *
     * @param  string  $name  ギルド名
     */
    public function selectByName(string $name): ?SysGuild
    {
        /** @var SysGuild|null */
        return $this->selectWithoutCache()->where('name', $name)->first();
    }

    /**
     * ギルド一覧を取得（キャッシュを通さない）
     *
     * 全件は返さない。並び順はレベルの高い順で、同レベルならID順。
     * タイトルごとに「アクティブ順」「募集中順」などへ差し替える想定。
     *
     * @param  int  $limit  取得件数
     * @param  int  $offset  読み飛ばす件数
     * @return Collection<array-key, SysGuild>
     */
    public function selectList(int $limit, int $offset): Collection
    {
        return $this->selectWithoutCache()
            ->orderByDesc('level')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * ギルドを作成（Model返却）
     *
     * @param  string  $name  ギルド名
     * @param  string  $description  ギルド説明
     * @param  int  $masterPlayerId  マスタープレイヤーID
     */
    public function insertGuild(string $name, string $description, int $masterPlayerId): SysGuild
    {
        $guild = new SysGuild;
        $guild->setName($name);
        $guild->setDescription($description);
        $guild->setLevel(1);
        $guild->setExp(0);
        $guild->setMaxMembers(30);
        $guild->exists = false;
        $this->setModel($guild);

        // マスターメンバーの登録と応答生成に採番済みのギルドIDが必要なため、
        // ここでキューをフラッシュしてIDを確定させる
        $this->flushQueue();

        // マスターメンバーを作成
        $this->sysGuildMemberRepository->insertMember(
            $guild->getId(),
            $masterPlayerId,
            GuildRole::MASTER
        );

        return $guild;
    }
}
