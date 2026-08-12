<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\OpenResponse;
use App\Repositories\Trx\TrxMailboxRepository;

/**
 * MailboxOpenUseCase
 *
 * メール既読処理
 */
class MailboxOpenUseCase extends _BaseUseCase
{
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
    ) {}

    /**
     * メールを既読にする
     *
     * @throws GameException
     */
    public function exec(int $sysPlayerId, int $trxMailboxId): OpenResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId) {
            // メールボックス取得
            $trxMailbox = $this->trxMailboxRepository->selectById($trxMailboxId);

            if ($trxMailbox === null || $trxMailbox->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'Mailbox not found');
            }

            // 既に開封済みの場合はそのまま返す（冪等性）
            if ($trxMailbox->getIsOpened()) {
                return new OpenResponse($trxMailbox->getId(), true);
            }

            // 既読にする
            $this->trxMailboxRepository->markAsOpened($trxMailbox);

            return new OpenResponse($trxMailbox->getId(), true);
        });
    }
}
