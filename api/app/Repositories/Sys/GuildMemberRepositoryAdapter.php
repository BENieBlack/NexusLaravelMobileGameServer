<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildMemberAdapter;
use App\Models\Sys\SysGuildMember;
use NexusGuild\DataTransferObjects\GuildMember;
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
    public function selectById(int $memberId): ?GuildMember
    {
        $sysGuildMember = $this->sysGuildMemberRepository->selectById($memberId);

        return $sysGuildMember ? GuildMemberAdapter::toDto($sysGuildMember) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildMember
    {
        $sysGuildMember = $this->sysGuildMemberRepository->selectByGuildAndPlayer($guildId, $playerId);

        return $sysGuildMember ? GuildMemberAdapter::toDto($sysGuildMember) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $playerId): ?GuildMember
    {
        $sysGuildMember = $this->sysGuildMemberRepository->selectByPlayerId($playerId);

        return $sysGuildMember ? GuildMemberAdapter::toDto($sysGuildMember) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<GuildMember>
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
    public function insert(int $guildId, int $playerId, string $role): GuildMember
    {
        $sysGuildMember = $this->sysGuildMemberRepository->insertMember($guildId, $playerId, $role);

        return GuildMemberAdapter::toDto($sysGuildMember);
    }

    /**
     * {@inheritDoc}
     */
    public function updateRole(GuildMember $member, string $role): GuildMember
    {
        $sysGuildMember = $this->sysGuildMemberRepository->selectById($member->getId());

        if ($sysGuildMember === null) {
            throw new \RuntimeException('Guild member not found');
        }

        $sysGuildMember->setRole($role);
        $this->sysGuildMemberRepository->setModel($sysGuildMember);

        return GuildMemberAdapter::toDto($sysGuildMember);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(GuildMember $member): void
    {
        $sysGuildMember = $this->sysGuildMemberRepository->selectById($member->getId());

        if ($sysGuildMember !== null) {
            $this->sysGuildMemberRepository->hardDeleteModel($sysGuildMember);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByPlayerId(int $playerId): void
    {
        $sysGuildMembers = SysGuildMember::where('sys_player_id', $playerId)->get();

        foreach ($sysGuildMembers as $sysGuildMember) {
            $this->sysGuildMemberRepository->hardDeleteModel($sysGuildMember);
        }
    }
}
