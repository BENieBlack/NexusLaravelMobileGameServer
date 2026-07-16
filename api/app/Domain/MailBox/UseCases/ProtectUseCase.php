<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\ProtectResponse;
use App\Repositories\Trx\TrxMailboxRepository;

/**
 * ProtectUseCase
 *
 * メール保護機能（削除防止）
 */
class ProtectUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
    ) {
    }

    /**
     * メールの保護状態を切り替える
     *
     * @param int $sysPlayerId
     * @param int $trxMailboxId
     * @param bool $isProtected
     * @return ProtectResponse
     * @throws GameException
     */
    public function handle(int $sysPlayerId, int $trxMailboxId, bool $isProtected): ProtectResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId, $isProtected) {
            // メールボックス取得
            $trxMailbox = $this->trxMailboxRepository->selectById($trxMailboxId);

            if ($trxMailbox === null || $trxMailbox->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'Mailbox not found');
            }

            // 削除済みメールは保護できない
            if ($trxMailbox->getIsDelete()) {
                throw new GameException(GameErrorCode::MAILBOX_ALREADY_DELETED, 'Mailbox already deleted');
            }

            // 保護状態を切り替え
            $this->trxMailboxRepository->toggleProtection($trxMailbox, $isProtected);

            return new ProtectResponse(
                trxMailboxId: $trxMailbox->getId(),
                isProtected: $isProtected,
                success: true
            );
        });
    }
}
