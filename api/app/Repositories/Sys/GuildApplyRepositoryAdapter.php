<?php

namespace App\Repositories\Sys;

use App\Adapters\Guild\GuildApplyAdapter;
use App\Models\Sys\SysGuildApply;
use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\DataTransferObjects\GuildApply;
use NexusGuild\Repositories\GuildApplyRepositoryInterface;

/**
 * GuildApplyRepositoryAdapter
 *
 * nexus-guildパッケージのGuildApplyRepositoryInterfaceを実装し、
 * Application層のSysGuildApplyRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 */
class GuildApplyRepositoryAdapter implements GuildApplyRepositoryInterface
{
    public function __construct(
        private readonly SysGuildApplyRepository $sysGuildApplyRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectById(int $applyId): ?GuildApply
    {
        $model = $this->sysGuildApplyRepository->selectById($applyId);

        return $model ? GuildApplyAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildApply
    {
        $model = $this->sysGuildApplyRepository->selectByGuildAndPlayer($guildId, $playerId);

        return $model ? GuildApplyAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<GuildApply>
     */
    public function selectAppliesByGuildId(int $guildId): array
    {
        return GuildApplyAdapter::toDtoArray(
            $this->sysGuildApplyRepository->selectAppliesByGuildId($guildId)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<GuildApply>
     */
    public function selectAppliesByPlayerId(int $playerId): array
    {
        return GuildApplyAdapter::toDtoArray(
            $this->sysGuildApplyRepository->selectAppliesByPlayerId($playerId)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function insert(int $guildId, int $playerId): GuildApply
    {
        $model = $this->sysGuildApplyRepository->insertApply($guildId, $playerId);

        return GuildApplyAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function accept(GuildApply $guildApplyDto): GuildApply
    {
        $model = $this->requireModel($guildApplyDto->getId());

        $model->setStatus(GuildApplyStatus::ACCEPTED);
        $this->sysGuildApplyRepository->setModel($model);

        return GuildApplyAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function reject(GuildApply $guildApplyDto): GuildApply
    {
        $model = $this->requireModel($guildApplyDto->getId());

        $model->setStatus(GuildApplyStatus::REJECTED);
        $this->sysGuildApplyRepository->setModel($model);

        return GuildApplyAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByPlayerId(int $playerId): void
    {
        $models = SysGuildApply::where('sys_player_id', $playerId)->get();

        foreach ($models as $model) {
            $this->sysGuildApplyRepository->hardDeleteModel($model);
        }
    }

    /**
     * 対象の加入申請を取得する（存在しなければ例外）
     */
    private function requireModel(int $applyId): SysGuildApply
    {
        $model = $this->sysGuildApplyRepository->selectById($applyId);

        if ($model === null) {
            throw new \RuntimeException('Guild apply not found');
        }

        return $model;
    }
}
