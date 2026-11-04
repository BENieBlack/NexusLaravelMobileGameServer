<?php

namespace App\Domain\Mailbox\Responses;

use App\Domain\_BaseResponse;

/**
 * LockResponse
 *
 * メールロックレスポンス
 */
class LockResponse extends _BaseResponse
{
    public function __construct(
        private int $trxMailboxId,
        private bool $isLocked,
    ) {}

    /**
     * レスポンス配列を取得
     */
    public function toArray(): array
    {
        return [
            'trx_mailbox_id' => $this->trxMailboxId,
            'is_locked' => $this->isLocked,
        ];
    }
}
