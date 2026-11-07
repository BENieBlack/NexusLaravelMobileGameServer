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
        $sysGuildApply = $this->sysGuildApplyRepository->selectById($applyId);

        return $sysGuildApply ? GuildApplyAdapter::toDto($sysGuildApply) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildApply
    {
        $sysGuildApply = $this->sysGuildApplyRepository->selectByGuildAndPlayer($guildId, $playerId);

        return $sysGuildApply ? GuildApplyAdapter::toDto($sysGuildApply) : null;
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
        $sysGuildApply = $this->sysGuildApplyRepository->insertApply($guildId, $playerId);

        return GuildApplyAdapter::toDto($sysGuildApply);
    }

    /**
     * {@inheritDoc}
     */
    public function accept(GuildApply $guildApply): GuildApply
    {
        $sysGuildApply = $this->requireModel($guildApply->getId());

        $sysGuildApply->setStatus(GuildApplyStatus::ACCEPTED);
        $this->sysGuildApplyRepository->setModel($sysGuildApply);

        return GuildApplyAdapter::toDto($sysGuildApply);
    }

    /**
     * {@inheritDoc}
     */
    public function reject(GuildApply $guildApply): GuildApply
    {
        $sysGuildApply = $this->requireModel($guildApply->getId());

        $sysGuildApply->setStatus(GuildApplyStatus::REJECTED);
        $this->sysGuildApplyRepository->setModel($sysGuildApply);

        return GuildApplyAdapter::toDto($sysGuildApply);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByPlayerId(int $playerId): void
    {
        $sysGuildApplys = SysGuildApply::where('sys_player_id', $playerId)->get();

        foreach ($sysGuildApplys as $sysGuildApply) {
            $this->sysGuildApplyRepository->hardDeleteModel($sysGuildApply);
        }
    }

    /**
     * 対象の加入申請を取得する（存在しなければ例外）
     */
    private function requireModel(int $applyId): SysGuildApply
    {
        $sysGuildApply = $this->sysGuildApplyRepository->selectById($applyId);

        if ($sysGuildApply === null) {
            throw new \RuntimeException('Guild apply not found');
        }

        return $sysGuildApply;
    }
}
