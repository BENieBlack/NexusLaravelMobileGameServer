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
        $model = $this->sysGuildRepository->selectById($guildId);

        return $model ? GuildAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByName(string $name): ?Guild
    {
        $model = $this->sysGuildRepository->selectByName($name);

        return $model ? GuildAdapter::toDto($model) : null;
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
        $model = $this->sysGuildRepository->insertGuild($name, $description, $masterPlayerId);

        return GuildAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Guild $guild, array $data): Guild
    {
        $model = $this->requireModel($guild->getId());

        if (isset($data['name'])) {
            $model->setName($data['name']);
        }
        if (isset($data['description'])) {
            $model->setDescription($data['description']);
        }
        if (isset($data['level'])) {
            $model->setLevel($data['level']);
        }
        if (isset($data['exp'])) {
            $model->setExp($data['exp']);
        }
        if (isset($data['max_members'])) {
            $model->setMaxMembers($data['max_members']);
        }

        $this->sysGuildRepository->setModel($model);

        return GuildAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Guild $guild): void
    {
        $model = $this->sysGuildRepository->selectById($guild->getId());

        if ($model !== null) {
            $this->sysGuildRepository->hardDeleteModel($model);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addExp(Guild $guild, int $exp): Guild
    {
        $model = $this->requireModel($guild->getId());

        $model->setExp($model->getExp() + $exp);
        $this->sysGuildRepository->setModel($model);

        return GuildAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function updateLevel(Guild $guild, int $level, int $exp): Guild
    {
        $model = $this->requireModel($guild->getId());

        $model->setLevel($level);
        $model->setExp($exp);
        $this->sysGuildRepository->setModel($model);

        return GuildAdapter::toDto($model);
    }

    /**
     * 対象ギルドを取得する（存在しなければ例外）
     */
    private function requireModel(int $guildId): SysGuild
    {
        $model = $this->sysGuildRepository->selectById($guildId);

        if ($model === null) {
            throw new \RuntimeException('Guild not found');
        }

        return $model;
    }
}
