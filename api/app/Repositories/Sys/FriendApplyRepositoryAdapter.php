<?php

namespace App\Repositories\Sys;

use App\Adapters\Friend\FriendApplyAdapter;
use App\Models\Sys\SysFriendApply;
use NexusFriend\DataTransferObjects\FriendApply;
use NexusFriend\Repositories\FriendApplyRepositoryInterface;

/**
 * FriendApplyRepositoryAdapter
 *
 * nexus-friendパッケージのFriendApplyRepositoryInterfaceを実装し、
 * Application層のSysFriendApplyRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 */
class FriendApplyRepositoryAdapter implements FriendApplyRepositoryInterface
{
    public function __construct(
        private readonly SysFriendApplyRepository $sysFriendApplyRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectById(int $friendApplyId): ?FriendApply
    {
        $sysFriendApply = $this->sysFriendApplyRepository->selectById($friendApplyId);

        return $sysFriendApply ? FriendApplyAdapter::toDto($sysFriendApply) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerPair(int $senderPlayerId, int $receiverPlayerId): ?FriendApply
    {
        $sysFriendApply = $this->sysFriendApplyRepository->selectByPlayerPair($senderPlayerId, $receiverPlayerId);

        return $sysFriendApply ? FriendApplyAdapter::toDto($sysFriendApply) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<FriendApply>
     */
    public function selectAppliesByPlayerId(int $playerId): array
    {
        return FriendApplyAdapter::toDtoArray(
            $this->sysFriendApplyRepository->selectAppliesByPlayerId($playerId)->all()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<FriendApply>
     */
    public function selectAcceptedFriendsByPlayerId(int $playerId): array
    {
        return FriendApplyAdapter::toDtoArray(
            $this->sysFriendApplyRepository->selectAcceptedFriendsByPlayerId($playerId)->all()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function insert(int $senderPlayerId, int $receiverPlayerId): FriendApply
    {
        $sysFriendApply = $this->sysFriendApplyRepository->insertApply($senderPlayerId, $receiverPlayerId);

        return FriendApplyAdapter::toDto($sysFriendApply);
    }

    /**
     * {@inheritDoc}
     */
    public function accept(FriendApply $friendApply): FriendApply
    {
        $sysFriendApply = $this->requireModel($friendApply->getId());

        $sysFriendApply->accept();
        $this->sysFriendApplyRepository->setModel($sysFriendApply);

        return FriendApplyAdapter::toDto($sysFriendApply);
    }

    /**
     * {@inheritDoc}
     */
    public function reject(FriendApply $friendApply): FriendApply
    {
        $sysFriendApply = $this->requireModel($friendApply->getId());

        $sysFriendApply->reject();
        $this->sysFriendApplyRepository->setModel($sysFriendApply);

        return FriendApplyAdapter::toDto($sysFriendApply);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteFriendRelation(int $playerId, int $targetPlayerId): ?FriendApply
    {
        $sysFriendApply = $this->sysFriendApplyRepository->deleteFriendRelationModel($playerId, $targetPlayerId);

        return $sysFriendApply ? FriendApplyAdapter::toDto($sysFriendApply) : null;
    }

    /**
     * 対象のフレンド申請を取得する（存在しなければ例外）
     */
    private function requireModel(int $friendApplyId): SysFriendApply
    {
        $sysFriendApply = $this->sysFriendApplyRepository->selectById($friendApplyId);

        if ($sysFriendApply === null) {
            throw new \RuntimeException('Friend apply not found');
        }

        return $sysFriendApply;
    }
}
