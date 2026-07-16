<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;

/**
 * ProtectResponse
 *
 * メール保護レスポンス
 */
class ProtectResponse extends _BaseResponse
{
    /**
     * @param int $trxMailboxId
     * @param bool $isProtected
     * @param bool $success
     */
    public function __construct(
        private int $trxMailboxId,
        private bool $isProtected,
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
            'is_protected' => $this->isProtected,
            'success' => $this->success,
        ];
    }
}
