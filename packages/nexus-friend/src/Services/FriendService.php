<?php

namespace NexusFriend\Services;

use NexusFriend\Constants\FriendStatus;
use NexusFriend\DataTransferObjects\FriendApply;
use NexusFriend\Exceptions\FriendException;
use NexusFriend\Repositories\FriendApplyRepositoryInterface;

/**
 * FriendService
 *
 * フレンド機能のビジネスロジック
 */
class FriendService
{
    public function __construct(
        private readonly FriendApplyRepositoryInterface $repository,
    ) {}

    /**
     * 重複申請がないかバリデーション
     *
     * 既に申請中またはフレンド関係がある場合は例外をスロー
     *
     * @param  int  $senderPlayerId  申請者プレイヤーID
     * @param  int  $receiverPlayerId  受信者プレイヤーID
     *
     * @throws FriendException 既に申請済みまたはフレンド関係がある場合
     */
    public function validateNoDuplicateApply(int $senderPlayerId, int $receiverPlayerId): void
    {
        $existingApply = $this->repository->selectByPlayerPair($senderPlayerId, $receiverPlayerId);

        if ($existingApply === null) {
            return;
        }

        if ($existingApply->getStatus() === FriendStatus::APPLIED) {
            throw FriendException::alreadyApplied();
        }

        if ($existingApply->getStatus() === FriendStatus::ACCEPTED) {
            throw FriendException::alreadyFriends();
        }
    }

    /**
     * 自分自身への申請でないかバリデーション
     *
     * @param  int  $senderPlayerId  申請者プレイヤーID
     * @param  int  $receiverPlayerId  受信者プレイヤーID
     *
     * @throws FriendException 自分自身への申請の場合
     */
    public function validateNotSelfApply(int $senderPlayerId, int $receiverPlayerId): void
    {
        if ($senderPlayerId === $receiverPlayerId) {
            throw FriendException::selfApply();
        }
    }

    /**
     * 受信者の承認/却下権限をバリデーション
     *
     * 申請の受信者のみが承認/却下できる
     *
     * @param  FriendApply  $friendApply  フレンド申請DTO
     * @param  int  $currentPlayerId  現在のプレイヤーID
     *
     * @throws FriendException 承認権限がない場合
     */
    public function validateReceiverAuthorization(FriendApply $friendApply, int $currentPlayerId): void
    {
        if ($friendApply->getReceiverPlayerId() !== $currentPlayerId) {
            throw FriendException::notAuthorized();
        }
    }

    /**
     * 申請が承認可能な状態かバリデーション
     *
     * @param  FriendApply  $friendApply  フレンド申請DTO
     *
     * @throws FriendException 承認できない状態の場合
     */
    public function validateCanAccept(FriendApply $friendApply): void
    {
        if ($friendApply->getStatus() === FriendStatus::ACCEPTED) {
            throw FriendException::alreadyAccepted();
        }

        if ($friendApply->getStatus() === FriendStatus::DELETED) {
            throw FriendException::alreadyDeleted();
        }

        if ($friendApply->getStatus() === FriendStatus::REJECTED) {
            throw FriendException::alreadyRejected();
        }
    }

    /**
     * 申請が却下可能な状態かバリデーション
     *
     * @param  FriendApply  $friendApply  フレンド申請DTO
     *
     * @throws FriendException 却下できない状態の場合
     */
    public function validateCanReject(FriendApply $friendApply): void
    {
        if ($friendApply->getStatus() === FriendStatus::ACCEPTED) {
            throw FriendException::alreadyAccepted();
        }

        if ($friendApply->getStatus() === FriendStatus::DELETED) {
            throw FriendException::alreadyDeleted();
        }

        if ($friendApply->getStatus() === FriendStatus::REJECTED) {
            throw FriendException::alreadyRejected();
        }
    }

    /**
     * フレンド申請を送信
     *
     * @param  int  $senderPlayerId  申請者プレイヤーID
     * @param  int  $receiverPlayerId  受信者プレイヤーID
     */
    public function sendApply(int $senderPlayerId, int $receiverPlayerId): FriendApply
    {
        $this->validateNotSelfApply($senderPlayerId, $receiverPlayerId);
        $this->validateNoDuplicateApply($senderPlayerId, $receiverPlayerId);

        return $this->repository->insert($senderPlayerId, $receiverPlayerId);
    }

    /**
     * フレンド申請を承認
     *
     * @param  int  $friendApplyId  フレンド申請ID
     * @param  int  $currentPlayerId  現在のプレイヤーID（受信者）
     *
     * @throws FriendException 申請が見つからない、または承認できない場合
     */
    public function acceptApply(int $friendApplyId, int $currentPlayerId): FriendApply
    {
        $apply = $this->repository->selectById($friendApplyId);

        if ($apply === null) {
            throw FriendException::applyNotFound();
        }

        $this->validateReceiverAuthorization($apply, $currentPlayerId);
        $this->validateCanAccept($apply);

        return $this->repository->accept($apply);
    }

    /**
     * フレンド申請を却下
     *
     * @param  int  $friendApplyId  フレンド申請ID
     * @param  int  $currentPlayerId  現在のプレイヤーID（受信者）
     *
     * @throws FriendException 申請が見つからない、または却下できない場合
     */
    public function rejectApply(int $friendApplyId, int $currentPlayerId): FriendApply
    {
        $apply = $this->repository->selectById($friendApplyId);

        if ($apply === null) {
            throw FriendException::applyNotFound();
        }

        $this->validateReceiverAuthorization($apply, $currentPlayerId);
        $this->validateCanReject($apply);

        return $this->repository->reject($apply);
    }

    /**
     * フレンド申請一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return array<FriendApply>
     */
    public function findApplyList(int $playerId): array
    {
        return $this->repository->selectAppliesByPlayerId($playerId);
    }

    /**
     * フレンド一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @return array<FriendApply>
     */
    public function findFriendList(int $playerId): array
    {
        return $this->repository->selectAcceptedFriendsByPlayerId($playerId);
    }

    /**
     * フレンド関係を削除
     *
     * @param  int  $playerId  削除実行者のプレイヤーID
     * @param  int  $targetPlayerId  削除対象のプレイヤーID
     *
     * @throws FriendException フレンド関係が見つからない場合
     */
    public function deleteFriend(int $playerId, int $targetPlayerId): FriendApply
    {
        $this->validateNotSelfApply($playerId, $targetPlayerId);

        $deletedRelation = $this->repository->deleteFriendRelation($playerId, $targetPlayerId);

        if ($deletedRelation === null) {
            throw FriendException::friendNotFound();
        }

        return $deletedRelation;
    }
}
