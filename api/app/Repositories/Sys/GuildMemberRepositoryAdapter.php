<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildMemberAdapter;
use App\Models\Sys\SysGuildMember;
use NexusGuild\Dto\GuildMemberDto;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;

/**
 * GuildMemberRepositoryAdapter
 *
 * nexus-guildパッケージのGuildMemberRepositoryInterfaceを実装し、
 * Application層のSysGuildMemberRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 */
class GuildMemberRepositoryAdapter implements GuildMemberRepositoryInterface
{
    public function __construct(
        private readonly SysGuildMemberRepository $sysGuildMemberRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectById(int $memberId): ?GuildMemberDto
    {
        $model = $this->sysGuildMemberRepository->selectById($memberId);

        return $model ? GuildMemberAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildMemberDto
    {
        $model = $this->sysGuildMemberRepository->selectByGuildAndPlayer($guildId, $playerId);

        return $model ? GuildMemberAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $playerId): ?GuildMemberDto
    {
        $model = $this->sysGuildMemberRepository->selectByPlayerId($playerId);

        return $model ? GuildMemberAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<GuildMemberDto>
     */
    public function selectByGuildId(int $guildId): array
    {
        return GuildMemberAdapter::toDtoArray(
            $this->sysGuildMemberRepository->selectByGuildId($guildId)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function countByGuildId(int $guildId): int
    {
        return SysGuildMember::where('sys_guild_id', $guildId)->count();
    }

    /**
     * {@inheritDoc}
     */
    public function insert(int $guildId, int $playerId, string $role): GuildMemberDto
    {
        $model = $this->sysGuildMemberRepository->insertMember($guildId, $playerId, $role);

        return GuildMemberAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function updateRole(GuildMemberDto $memberDto, string $role): GuildMemberDto
    {
        $model = $this->sysGuildMemberRepository->selectById($memberDto->getId());

        if ($model === null) {
            throw new \RuntimeException('Guild member not found');
        }

        $model->setRole($role);
        $this->sysGuildMemberRepository->setModel($model);

        return GuildMemberAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(GuildMemberDto $memberDto): void
    {
        $model = $this->sysGuildMemberRepository->selectById($memberDto->getId());

        if ($model !== null) {
            $this->sysGuildMemberRepository->hardDeleteModel($model);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByPlayerId(int $playerId): void
    {
        $models = SysGuildMember::where('sys_player_id', $playerId)->get();

        foreach ($models as $model) {
            $this->sysGuildMemberRepository->hardDeleteModel($model);
        }
    }
}
