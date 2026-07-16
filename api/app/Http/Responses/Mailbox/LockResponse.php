<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;

/**
 * LockResponse
 *
 * メールロックレスポンス
 */
class LockResponse extends _BaseResponse
{
    /**
     * @param int $trxMailboxId
     * @param bool $isLocked
     * @param bool $success
     */
    public function __construct(
        private int $trxMailboxId,
        private bool $isLocked,
        private bool $success,
    ) {
    }

    /**
     * レスポンス配列を取得
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'trx_mailbox_id' => $this->trxMailboxId,
            'is_locked' => $this->isLocked,
            'success' => $this->success,
        ];
    }
}
