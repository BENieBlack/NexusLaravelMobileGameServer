<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\LockResponse;
use App\Repositories\Trx\TrxMailboxRepository;

/**
 * MailboxLockUseCase
 *
 * メールロック機能（削除防止）
 */
class MailboxLockUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
    ) {}

    /**
     * メールのロック状態を切り替える
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $trxMailboxId, bool $isLocked): LockResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId, $isLocked) {
            // メールボックス取得
            $trxMailbox = $this->trxMailboxRepository->selectById($trxMailboxId);

            if ($trxMailbox === null || $trxMailbox->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'Mailbox not found');
            }

            // 削除済みメールはロックできない
            if ($trxMailbox->getIsDelete()) {
                throw new GameException(GameErrorCode::MAILBOX_ALREADY_DELETED, 'Mailbox already deleted');
            }

            // ロック状態を切り替え
            $this->trxMailboxRepository->toggleProtection($trxMailbox, $isLocked);

            return new LockResponse(
                trxMailboxId: $trxMailbox->getId(),
                isLocked: $isLocked,
                success: true
            );
        });
    }
}
