<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildAdapter;
use App\Models\Sys\SysGuild;
use NexusGuild\DataTransferObjects\Guild;
use NexusGuild\Repositories\GuildRepositoryInterface;

/**
 * GuildRepositoryAdapter
 *
 * nexus-guildパッケージのGuildRepositoryInterfaceを実装し、
 * Application層のSysGuildRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 */
class GuildRepositoryAdapter implements GuildRepositoryInterface
{
    public function __construct(
        private readonly SysGuildRepository $sysGuildRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectById(int $guildId): ?Guild
    {
        $sysGuild = $this->sysGuildRepository->selectById($guildId);

        return $sysGuild ? GuildAdapter::toDto($sysGuild) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByName(string $name): ?Guild
    {
        $sysGuild = $this->sysGuildRepository->selectByName($name);

        return $sysGuild ? GuildAdapter::toDto($sysGuild) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<Guild>
     */
    public function selectList(int $limit, int $offset): array
    {
        return GuildAdapter::toDtoArray($this->sysGuildRepository->selectList($limit, $offset));
    }

    /**
     * {@inheritDoc}
     */
    public function insert(string $name, string $description, int $masterPlayerId): Guild
    {
        $sysGuild = $this->sysGuildRepository->insertGuild($name, $description, $masterPlayerId);

        return GuildAdapter::toDto($sysGuild);
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Guild $guild, array $data): Guild
    {
        $sysGuild = $this->requireModel($guild->getId());

        if (isset($data['name'])) {
            $sysGuild->setName($data['name']);
        }
        if (isset($data['description'])) {
            $sysGuild->setDescription($data['description']);
        }
        if (isset($data['level'])) {
            $sysGuild->setLevel($data['level']);
        }
        if (isset($data['exp'])) {
            $sysGuild->setExp($data['exp']);
        }
        if (isset($data['max_members'])) {
            $sysGuild->setMaxMembers($data['max_members']);
        }

        $this->sysGuildRepository->setModel($sysGuild);

        return GuildAdapter::toDto($sysGuild);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Guild $guild): void
    {
        $sysGuild = $this->sysGuildRepository->selectById($guild->getId());

        if ($sysGuild !== null) {
            $this->sysGuildRepository->hardDeleteModel($sysGuild);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addExp(Guild $guild, int $exp): Guild
    {
        $sysGuild = $this->requireModel($guild->getId());

        $sysGuild->setExp($sysGuild->getExp() + $exp);
        $this->sysGuildRepository->setModel($sysGuild);

        return GuildAdapter::toDto($sysGuild);
    }

    /**
     * {@inheritDoc}
     */
    public function updateLevel(Guild $guild, int $level, int $exp): Guild
    {
        $sysGuild = $this->requireModel($guild->getId());

        $sysGuild->setLevel($level);
        $sysGuild->setExp($exp);
        $this->sysGuildRepository->setModel($sysGuild);

        return GuildAdapter::toDto($sysGuild);
    }

    /**
     * 対象ギルドを取得する（存在しなければ例外）
     */
    private function requireModel(int $guildId): SysGuild
    {
        $sysGuild = $this->sysGuildRepository->selectById($guildId);

        if ($sysGuild === null) {
            throw new \RuntimeException('Guild not found');
        }

        return $sysGuild;
    }
}
