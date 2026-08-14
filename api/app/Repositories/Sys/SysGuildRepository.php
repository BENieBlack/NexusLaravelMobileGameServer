<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysGuild;
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
     * IDでギルドを検索（Model返却）
     *
     * @param  int  $guildId  ギルドID
     */
    public function selectById(int $guildId): ?SysGuild
    {
        return SysGuild::find($guildId);
    }

    /**
     * ギルド名で検索（Model返却）
     *
     * @param  string  $name  ギルド名
     */
    public function selectByName(string $name): ?SysGuild
    {
        return SysGuild::where('name', $name)->first();
    }

    /**
     * 全ギルド一覧を取得（Model返却）
     *
     * @return Collection<SysGuild>
     */
    public function selectAll(): Collection
    {
        return SysGuild::all();
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
