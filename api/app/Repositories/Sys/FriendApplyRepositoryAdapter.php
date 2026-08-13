<?php

namespace App\Repositories\Sys;

use App\Adapters\Friend\FriendApplyAdapter;
use App\Models\Sys\SysFriendApply;
use NexusFriend\Dto\FriendApplyDto;
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
    public function selectById(int $friendApplyId): ?FriendApplyDto
    {
        $model = $this->sysFriendApplyRepository->selectById($friendApplyId);

        return $model ? FriendApplyAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerPair(int $senderPlayerId, int $receiverPlayerId): ?FriendApplyDto
    {
        $model = $this->sysFriendApplyRepository->selectByPlayerPair($senderPlayerId, $receiverPlayerId);

        return $model ? FriendApplyAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<FriendApplyDto>
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
     * @return array<FriendApplyDto>
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
    public function insert(int $senderPlayerId, int $receiverPlayerId): FriendApplyDto
    {
        $model = $this->sysFriendApplyRepository->insertApply($senderPlayerId, $receiverPlayerId);

        return FriendApplyAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function accept(FriendApplyDto $friendApplyDto): FriendApplyDto
    {
        $model = $this->requireModel($friendApplyDto->getId());

        $model->accept();
        $this->sysFriendApplyRepository->setModel($model);

        return FriendApplyAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function reject(FriendApplyDto $friendApplyDto): FriendApplyDto
    {
        $model = $this->requireModel($friendApplyDto->getId());

        $model->reject();
        $this->sysFriendApplyRepository->setModel($model);

        return FriendApplyAdapter::toDto($model);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteFriendRelation(int $playerId, int $targetPlayerId): ?FriendApplyDto
    {
        $model = $this->sysFriendApplyRepository->deleteFriendRelationModel($playerId, $targetPlayerId);

        return $model ? FriendApplyAdapter::toDto($model) : null;
    }

    /**
     * 対象のフレンド申請を取得する（存在しなければ例外）
     */
    private function requireModel(int $friendApplyId): SysFriendApply
    {
        $model = $this->sysFriendApplyRepository->selectById($friendApplyId);

        if ($model === null) {
            throw new \RuntimeException('Friend apply not found');
        }

        return $model;
    }
}
