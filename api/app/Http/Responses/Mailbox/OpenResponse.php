<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;

/**
 * OpenResponse
 *
 * メール既読レスポンス
 */
class OpenResponse extends _BaseResponse
{
    public function __construct(
        private int $trxMailboxId,
        private bool $isOpened,
    ) {}

    /**
     * レスポンス配列を取得
     */
    public function toArray(): array
    {
        return [
            'trx_mailbox_id' => $this->trxMailboxId,
            'is_opened' => $this->isOpened,
        ];
    }
}
