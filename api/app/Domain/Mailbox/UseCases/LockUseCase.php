<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Mailbox\Responses\LockResponse;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Repositories\Trx\TrxMailboxRepository;

/**
 * LockUseCase
 *
 * メールロック機能（削除防止）
 */
class LockUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
    ) {}

    /**
     * メールのロック状態を切り替える
     *
     * @throws GameException
     */
    public function handle(int $sysPlayerId, int $trxMailboxId, bool $isLocked): LockResponse
    {
        $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId, $isLocked) {
            // バッチ（mailbox:delete-expired）も同じ行の is_protected / is_delete を書くため、
            // FOR UPDATE で読み直してから判定・更新する（ロックの外で読んだ値で書かない）。
            // 先に押さえた側が終わるまで待つので、削除済みかどうかの判定も最新の値になる
            $trxMailbox = $this->trxMailboxRepository->selectByIdForUpdate($trxMailboxId);

            if ($trxMailbox === null || $trxMailbox->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'Mailbox not found');
            }

            // 削除済みメールはロックできない
            if ($trxMailbox->getIsDelete()) {
                throw new GameException(GameErrorCode::MAILBOX_ALREADY_DELETED, 'Mailbox already deleted');
            }

            // ロック状態を切り替え
            $this->trxMailboxRepository->toggleProtection($trxMailbox, $isLocked);
        });

        return new LockResponse(trxMailboxId: $trxMailboxId, isLocked: $isLocked);
    }
}
